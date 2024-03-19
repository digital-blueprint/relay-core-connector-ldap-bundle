<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Ldap;

use Dbp\Relay\CoreBundle\Rest\Options;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\Nodes\ConditionNode as ConditionFilterNode;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\Nodes\LogicalNode as LogicalFilterNode;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\Nodes\Node as FilterNode;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\Nodes\NodeType as FilterNodeType;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\Nodes\OperatorType as FilterOperatorType;
use Dbp\Relay\CoreConnectorLdapBundle\DependencyInjection\Configuration;
use LdapRecord\Auth\BindException;
use LdapRecord\Configuration\ConfigurationException;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Model;
use LdapRecord\Models\OpenLDAP\User;
use LdapRecord\Query\Builder;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Cache\Psr16Cache;

class LdapConnection implements LoggerAwareInterface, LdapConnectionInterface
{
    use LoggerAwareTrait;

    private ?CacheItemPoolInterface $cacheItemPool;
    private int $cacheTtl;
    private array $connectionConfig = [];
    private string $identifierAttributeName = 'cn';
    private ?Connection $connection = null;

    private static function addFilterToQuery(Builder $queryBuilder, FilterNode $filterNode)
    {
        if ($filterNode instanceof LogicalFilterNode) {
            switch ($filterNode->getNodeType()) {
                case FilterNodeType::AND:
                    $queryBuilder->andFilter(function (Builder $builder) use ($filterNode) {
                        foreach ($filterNode->getChildren() as $childNodeDefinition) {
                            self::addFilterToQuery($builder, $childNodeDefinition);
                        }
                    });
                    break;
                case FilterNodeType::OR:
                    $queryBuilder->orFilter(function (Builder $builder) use ($filterNode) {
                        foreach ($filterNode->getChildren() as $childNodeDefinition) {
                            self::addFilterToQuery($builder, $childNodeDefinition);
                        }
                    });
                    break;
                case FilterNodeType::NOT:
                    $queryBuilder->notFilter(function (Builder $builder) use ($filterNode) {
                        foreach ($filterNode->getChildren() as $childNodeDefinition) {
                            self::addFilterToQuery($builder, $childNodeDefinition);
                        }
                    });
                    break;
                default:
                    throw new \InvalidArgumentException('invalid filter node type: '.$filterNode->getNodeType());
            }
        } elseif ($filterNode instanceof ConditionFilterNode) {
            $field = $filterNode->getField();
            $value = $filterNode->getValue();
            switch ($filterNode->getOperator()) {
                case FilterOperatorType::I_CONTAINS_OPERATOR:
                    $queryBuilder->whereContains($field, $value);
                    break;
                case FilterOperatorType::EQUALS_OPERATOR: // TODO: case-sensitivity post-precessing required
                    $queryBuilder->whereEquals($field, $value);
                    break;
                case FilterOperatorType::I_STARTS_WITH_OPERATOR:
                    $queryBuilder->whereStartsWith($field, $value);
                    break;
                case FilterOperatorType::I_ENDS_WITH_OPERATOR:
                    $queryBuilder->whereEndsWith($field, $value);
                    break;
                case FilterOperatorType::GREATER_THAN_OR_EQUAL_OPERATOR:
                    $queryBuilder->where($field, $queryBuilder->getGrammar()->getOperators()['>='], $value);
                    break;
                case FilterOperatorType::LESS_THAN_OR_EQUAL_OPERATOR:
                    $queryBuilder->where($field, $queryBuilder->getGrammar()->getOperators()['<='], $value);
                    break;
                case FilterOperatorType::IN_ARRAY_OPERATOR:
                    if (!is_array($value)) {
                        throw new \RuntimeException('filter condition operator "'.FilterOperatorType::IN_ARRAY_OPERATOR.'" requires an array type value');
                    }
                    $queryBuilder->whereIn($field, $value);
                    break;
                case FilterOperatorType::IS_NULL_OPERATOR:
                    $queryBuilder->whereHas($field);
                    break;
                default:
                    throw new \UnexpectedValueException('unsupported filter condition operator: '.$filterNode->getOperator());
            }
        }
    }

    public function __construct(array $config, ?CacheItemPoolInterface $cacheItemPool = null, int $ttl = 0)
    {
        $this->cacheItemPool = $ttl > 0 ? $cacheItemPool : null;
        $this->cacheTtl = $ttl;

        $this->loadConfig($config);
    }

    public function checkConnection()
    {
        $this->getCachedBuilder()->first();
    }

    /**
     * @throws LdapException
     */
    public function checkAttributesExist(): void
    {
        $attributes = [
            $this->identifierAttributeName,
        ];

        $missing = [];
        foreach ($attributes as $attribute) {
            if ($attribute !== '' && !$this->checkAttributeExists($attribute)) {
                $missing[] = $attribute;
            }
        }

        if (count($missing) > 0) {
            throw new LdapException('The following LDAP attributes were not found: '.join(', ', $missing),
                LdapException::USER_ATTRIBUTE_UNDEFINED);
        }
    }

    public function setCache(CacheItemPoolInterface $cacheItemPool, int $ttl)
    {
        $this->cacheItemPool = $ttl > 0 ? $cacheItemPool : null;
        $this->cacheTtl = $ttl;
    }

    /*
     * @throws LdapException
     */
    public function getUserAttributesByAttribute(string $userAttributeName, string $userAttributeValue): array
    {
        return $this->getUserAttributesByAttributeInternal($userAttributeName, $userAttributeValue);
    }

    /*
     * @throws LdapException
     */
    public function getUserAttributesByIdentifier(string $identifier): array
    {
        return $this->getUserAttributesByAttributeInternal($this->identifierAttributeName, $identifier);
    }

    /**
     * @return array[]
     *
     * @throws LdapException
     */
    public function getUsers(int $currentPageNumber, int $maxNumItemsPerPage, array $options = []): array
    {
        try {
            $query = $this->getCachedBuilder()
                ->model(new User())
                ->whereEquals('objectClass', 'person');

            if ($filter = Options::getFilter($options)) {
                self::addFilterToQuery($query, $filter->getRootNode());
            }

            $users = [];
            foreach ($query->forPage($currentPageNumber, $maxNumItemsPerPage) as $entry) {
                $users[] = $entry->getAttributes();
            }

            return $users;
        } catch (\Exception $exception) {
            // There was an issue binding / connecting to the server.
            throw new LdapException(sprintf('LDAP query failed. Message: %s', $exception->getMessage()),
                LdapException::SERVER_CONNECTION_FAILED);
        }
    }

    /*
     * @throws LdapException
     */
    protected function getUserAttributesByAttributeInternal(string $userAttributeName, string $userAttributeValue): array
    {
        if ($userAttributeName === '') {
            throw new LdapException('key user attribute must not be empty',
                LdapException::USER_ATTRIBUTE_UNDEFINED);
        }

        return $this->getUserEntry($userAttributeName, $userAttributeValue)->getAttributes();
    }

    /**
     * @throws LdapException
     */
    private function checkAttributeExists(string $attribute): bool
    {
        try {
            $entry = $this->getCachedBuilder()
                ->whereEquals('objectClass', 'person')
                ->whereHas($attribute)
                ->first();
        } catch (\Exception $exception) {
            // There was an issue binding / connecting to the server.
            throw new LdapException(sprintf('LDAP server connection failed. Message: %s', $exception->getMessage()),
                LdapException::SERVER_CONNECTION_FAILED);
        }

        return $entry !== null;
    }

    /**
     * @throws LdapRecordException
     * @throws BindException
     * @throws ConfigurationException
     */
    private function connect(): Connection
    {
        if ($this->connection === null) {
            if ($this->logger !== null) {
                Container::getInstance()->setLogger($this->logger);
            }
            $connection = new Connection($this->connectionConfig);
            if ($this->cacheItemPool !== null) {
                $connection->setCache(new Psr16Cache($this->cacheItemPool));
            }
            $connection->connect();

            $this->connection = $connection;
        }

        return $this->connection;
    }

    /**
     * @throws LdapRecordException
     * @throws BindException
     * @throws ConfigurationException
     */
    private function getCachedBuilder(): Builder
    {
        try {
            $until = (new \DateTimeImmutable())->add(new \DateInterval('PT'.$this->cacheTtl.'S'));
        } catch (\Exception $exception) {
            throw new \RuntimeException($exception->getMessage());
        }

        return $this->connect()->query()->cache($until);
    }

    /*
     * @throws LdapException
     */
    private function getUserEntry(string $userAttributeName, string $userAttributeValue): Model
    {
        try {
            $entry = $this->getCachedBuilder()
                ->model(new User())
                ->whereEquals('objectClass', 'person')
                ->whereEquals($userAttributeName, $userAttributeValue)
                ->first();

            if ($entry === null) {
                throw new LdapException(sprintf("User with '%s' attribute value '%s' could not be found!",
                    $userAttributeName, $userAttributeValue), LdapException::USER_NOT_FOUND);
            }

            return $entry;
        } catch (\Exception $exception) {
            // There was an issue binding / connecting to the server.
            throw new LdapException(sprintf('LDAP server connection failed. Message: %s', $exception->getMessage()),
                LdapException::SERVER_CONNECTION_FAILED);
        }
    }

    private function loadConfig(array $config)
    {
        $this->identifierAttributeName =
            $config[Configuration::LDAP_ATTRIBUTES_ATTRIBUTE][Configuration::LDAP_ATTRIBUTES_IDENTIFIER_ATTRIBUTE] ?? 'cn';

        $this->connectionConfig = [
            'hosts' => [$config[Configuration::LDAP_HOST_ATTRIBUTE] ?? ''],
            'base_dn' => $config[Configuration::LDAP_BASE_DN_ATTRIBUTE] ?? '',
            'username' => $config[Configuration::LDAP_USERNAME_ATTRIBUTE] ?? '',
            'password' => $config[Configuration::LDAP_PASSWORD_ATTRIBUTE] ?? '',
        ];

        $encryption = $config[Configuration::LDAP_ENCRYPTION_ATTRIBUTE] ?? 'start_tls';
        assert(in_array($encryption, ['start_tls', 'simple_tls', 'plain'], true));
        $this->connectionConfig['use_tls'] = ($encryption === 'start_tls');
        $this->connectionConfig['use_ssl'] = ($encryption === 'simple_tls');
        $this->connectionConfig['port'] = ($encryption === 'start_tls' || $encryption === 'plain') ? 389 : 636;
    }
}
