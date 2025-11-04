<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Ldap;

use Dbp\Relay\CoreBundle\Rest\Options;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\Nodes\ConditionNode as ConditionFilterNode;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\Nodes\LogicalNode as LogicalFilterNode;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\Nodes\Node as FilterNode;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\Nodes\NodeType as FilterNodeType;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\Nodes\OperatorType as FilterOperatorType;
use Dbp\Relay\CoreBundle\Rest\Query\Sort\Sort;
use Dbp\Relay\CoreConnectorLdapBundle\DependencyInjection\Configuration;
use LdapRecord\Auth\BindException;
use LdapRecord\Configuration\ConfigurationException;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\OpenLDAP\User;
use LdapRecord\Query\Builder;
use LdapRecord\Testing\ConnectionFake;
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
    private ?string $objectClass = null;
    protected ?Connection $connection = null;
    private int $numResultItemsWillSort = Configuration::LDAP_NUM_RESULT_ITEMS_WILL_SORT_LIMIT_DEFAULT;

    public static function toLdapRecordConnectionConfig(array $config): array
    {
        $connectionConfig = [
            'hosts' => [$config[Configuration::LDAP_HOST_ATTRIBUTE] ?? ''],
            'base_dn' => $config[Configuration::LDAP_BASE_DN_ATTRIBUTE] ?? '',
            'username' => $config[Configuration::LDAP_USERNAME_ATTRIBUTE] ?? '',
            'password' => $config[Configuration::LDAP_PASSWORD_ATTRIBUTE] ?? '',
        ];

        $encryption = $config[Configuration::LDAP_ENCRYPTION_ATTRIBUTE] ?? 'start_tls';
        assert(in_array($encryption, ['start_tls', 'simple_tls', 'plain'], true));
        $connectionConfig['use_tls'] = ($encryption === 'start_tls');
        $connectionConfig['use_ssl'] = ($encryption === 'simple_tls');
        $connectionConfig['port'] = ($encryption === 'start_tls' || $encryption === 'plain') ? 389 : 636;

        return $connectionConfig;
    }

    private static function assertIsNonEmptyArrayValue(mixed $value): void
    {
        if (false === is_array($value) || $value === []) {
            throw new LdapException('filter condition operator "'.
                FilterOperatorType::IN_ARRAY_OPERATOR.'" requires a non-empty array value',
                LdapException::FILTER_INVALID);
        }
    }

    private static function assertIsNonEmptyStringValue(mixed $value, string $filterOperatorType): void
    {
        if (false === is_string($value) || $value === '') {
            throw new LdapException('filter condition operator "'.
                $filterOperatorType.'" requires a non-empty string value',
                LdapException::FILTER_INVALID);
        }
    }

    /**
     * @throws LdapException
     */
    private static function addFilterToQuery(Builder $queryBuilder, FilterNode $filterNode): void
    {
        if ($filterNode instanceof LogicalFilterNode) {
            switch ($filterNode->getNodeType()) {
                case FilterNodeType::AND:
                    if (($numChildren = count($filterNode->getChildren())) > 1) {
                        $queryBuilder->andFilter(function (Builder $builder) use ($filterNode) {
                            foreach ($filterNode->getChildren() as $childNodeDefinition) {
                                self::addFilterToQuery($builder, $childNodeDefinition);
                            }
                        });
                    } elseif ($numChildren === 1) {
                        self::addFilterToQuery($queryBuilder, $filterNode->getChildren()[0]);
                    }
                    break;
                case FilterNodeType::OR:
                    if (($numChildren = count($filterNode->getChildren())) > 1) {
                        $queryBuilder->orFilter(function (Builder $builder) use ($filterNode) {
                            foreach ($filterNode->getChildren() as $childNodeDefinition) {
                                self::addFilterToQuery($builder, $childNodeDefinition);
                            }
                        });
                    } elseif ($numChildren === 1) {
                        self::addFilterToQuery($queryBuilder, $filterNode->getChildren()[0]);
                    }
                    break;
                case FilterNodeType::NOT:
                    if (count($filterNode->getChildren()) === 1) {
                        $queryBuilder->notFilter(function (Builder $builder) use ($filterNode) {
                            foreach ($filterNode->getChildren() as $childNodeDefinition) {
                                self::addFilterToQuery($builder, $childNodeDefinition);
                            }
                        });
                    } else {
                        throw new LdapException('invalid filter: NOT nodes may only have exactly one child node', LdapException::FILTER_INVALID);
                    }
                    break;
                default:
                    throw new LdapException('invalid filter node type: '.$filterNode->getNodeType(), LdapException::FILTER_INVALID);
            }
        } elseif ($filterNode instanceof ConditionFilterNode) {
            $field = $filterNode->getPath();
            $value = $filterNode->getValue();
            switch ($filterNode->getOperator()) {
                case FilterOperatorType::I_CONTAINS_OPERATOR:
                    self::assertIsNonEmptyStringValue($value, $filterNode->getOperator());
                    $queryBuilder->whereContains($field, $value);
                    break;
                case FilterOperatorType::EQUALS_OPERATOR: // TODO: case-sensitivity post-precessing required
                    $queryBuilder->whereEquals($field, (string) $value);
                    break;
                case FilterOperatorType::I_STARTS_WITH_OPERATOR:
                    self::assertIsNonEmptyStringValue($value, $filterNode->getOperator());
                    $queryBuilder->whereStartsWith($field, $value);
                    break;
                case FilterOperatorType::I_ENDS_WITH_OPERATOR:
                    self::assertIsNonEmptyStringValue($value, $filterNode->getOperator());
                    $queryBuilder->whereEndsWith($field, $value);
                    break;
                case FilterOperatorType::GREATER_THAN_OR_EQUAL_OPERATOR:
                    $queryBuilder->where($field, $queryBuilder->getGrammar()->getOperators()['>='], $value);
                    break;
                case FilterOperatorType::LESS_THAN_OR_EQUAL_OPERATOR:
                    $queryBuilder->where($field, $queryBuilder->getGrammar()->getOperators()['<='], $value);
                    break;
                case FilterOperatorType::IN_ARRAY_OPERATOR:
                    self::assertIsNonEmptyArrayValue($value);
                    $queryBuilder->whereIn($field, $value);
                    break;
                case FilterOperatorType::IS_NULL_OPERATOR:
                    $queryBuilder->whereHas($field);
                    break;
                default:
                    throw new LdapException('unsupported filter condition operator: '.$filterNode->getOperator(),
                        LdapException::FILTER_INVALID);
            }
        }
    }

    public function __construct(array $config, ?CacheItemPoolInterface $cacheItemPool = null, int $ttl = 0)
    {
        $this->cacheItemPool = $ttl > 0 ? $cacheItemPool : null;
        $this->cacheTtl = $ttl;

        $this->loadConfig($config);
    }

    /**
     * @throws LdapException
     */
    public function checkConnection(): void
    {
        try {
            $this->getCachedBuilder()->first();
        } catch (\Exception $exception) {
            throw new LdapException('LDAP connection failed: '.$exception->getMessage(), LdapException::SERVER_CONNECTION_FAILED);
        }
    }

    /**
     * @throws LdapException
     */
    public function assertAttributesExist(array $attributes = []): void
    {
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

    public function setCache(CacheItemPoolInterface $cacheItemPool, int $ttl): void
    {
        $this->cacheItemPool = $ttl > 0 ? $cacheItemPool : null;
        $this->cacheTtl = $ttl;
    }

    /*
     * @throws LdapException
     */
    public function getEntryByAttribute(string $attributeName, string $attributeValue): LdapEntryInterface
    {
        return $this->getEntryByAttributeInternal($attributeName, $attributeValue);
    }

    /**
     * @throws LdapException
     */
    public function getEntries(int $currentPageNumber = 1, int $maxNumItemsPerPage = 30, array $options = []): array
    {
        return $this->getEntriesInternal($currentPageNumber, $maxNumItemsPerPage, $options);
    }

    /*
     * @throws LdapException
     */
    protected function getEntryByAttributeInternal(string $attributeName, string $attributeValue): LdapEntryInterface
    {
        if ($attributeName === '') {
            throw new LdapException('key user attribute must not be empty',
                LdapException::USER_ATTRIBUTE_UNDEFINED);
        }

        return $this->getEntry($attributeName, $attributeValue);
    }

    /**
     * @throws LdapException
     */
    protected function getEntriesInternal(int $currentPageNumber, int $maxNumItemsPerPage, array $options = []): array
    {
        try {
            $query = $this->getCachedBuilder()
                ->model(new User())
                ->whereEquals('objectClass', $this->objectClass);

            if ($filter = Options::getFilter($options)) {
                self::addFilterToQuery($query, $filter->getRootNode());
            }

            $sortFields = Options::getSort($options)?->getSortFields();
            if (false === empty($sortFields)) {
                $allResults = $query->get(); // even this is likely to exhaust memory for large result sets
                if (count($allResults) > $this->numResultItemsWillSort) {
                    throw new LdapException('Too many results to sort', LdapException::TOO_MANY_RESULTS_TO_SORT);
                }
                $allResults = $allResults->sortBy(array_map(
                    function ($sortField) {
                        return [Sort::getPath($sortField), Sort::getDirection($sortField) === Sort::ASCENDING_DIRECTION ? 'asc' : 'desc'];
                    }, $sortFields));
                $resultEntries = $allResults->forPage($currentPageNumber, $maxNumItemsPerPage);
            } else {
                $resultEntries = [];
                $query->chunk($maxNumItemsPerPage,
                    function (iterable $chunkEntries, int $currentChunkNumber) use ($currentPageNumber, &$resultEntries) {
                        $done = $currentChunkNumber === $currentPageNumber;
                        if ($done) {
                            $resultEntries = $chunkEntries;
                        }

                        return false === $done;
                    });
            }

            $entries = [];
            foreach ($resultEntries as $entry) {
                $entries[] = new LdapEntry($entry);
            }

            return $entries;
        } catch (LdapRecordException $exception) {
            // There was an issue binding / connecting to the server.
            throw new LdapException(sprintf('LDAP query failed. Message: %s', $exception->getMessage()),
                LdapException::SERVER_CONNECTION_FAILED);
        }
    }

    /**
     * @throws LdapException
     */
    private function checkAttributeExists(string $attribute): bool
    {
        try {
            $entry = $this->getCachedBuilder()
                ->whereEquals('objectClass', $this->objectClass)
                ->whereHas($attribute)
                ->first();
        } catch (LdapRecordException $exception) {
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
    private function getConnectionInternal(): Connection
    {
        if ($this->connection === null) {
            if ($this->logger !== null) {
                Container::getInstance()->setLogger($this->logger);
            }
            $connection = new Connection($this->connectionConfig);
            if ($this->cacheItemPool !== null) {
                $connection->setCache(new Psr16Cache($this->cacheItemPool));
            }
            $this->connection = $connection;
        }

        if (false === $this->connection->isConnected()) {
            $this->connection->connect();
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

        return $this->getConnectionInternal()->query()->cache($until);
    }

    /*
     * @throws LdapException
     */
    private function getEntry(string $attributeName, string $attributeValue): LdapEntryInterface
    {
        try {
            $entry = $this->getCachedBuilder()
                ->model(new User())
                ->whereEquals('objectClass', $this->objectClass)
                ->whereEquals($attributeName, $attributeValue)
                ->first();
        } catch (LdapRecordException $exception) {
            // There was an issue binding / connecting to the server.
            throw new LdapException(sprintf('LDAP server connection failed. Message: %s', $exception->getMessage()),
                LdapException::SERVER_CONNECTION_FAILED);
        }

        if ($entry === null) {
            throw new LdapException(sprintf("User with '%s' attribute value '%s' could not be found!",
                $attributeName, $attributeValue), LdapException::ENTRY_NOT_FOUND);
        }

        return new LdapEntry($entry);
    }

    private function loadConfig(array $config): void
    {
        $this->objectClass =
            $config[Configuration::LDAP_OBJECT_CLASS_ATTRIBUTE] ?? 'person';
        $this->numResultItemsWillSort = $config[Configuration::LDAP_NUM_RESULT_ITEMS_WILL_SORT_LIMIT_ATTRIBUTE] ??
            Configuration::LDAP_NUM_RESULT_ITEMS_WILL_SORT_LIMIT_DEFAULT;

        $this->connectionConfig = self::toLdapRecordConnectionConfig($config);
    }

    public function setMockConnection(ConnectionFake $connectionFake): void
    {
        $this->connection = $connectionFake;
    }

    /**
     * For testing purposes only.
     */
    public function getConnection(): ?Connection
    {
        return $this->connection;
    }

    /**
     * For testing purposes only.
     */
    public function getConnectionConfig(): array
    {
        return $this->connectionConfig;
    }
}
