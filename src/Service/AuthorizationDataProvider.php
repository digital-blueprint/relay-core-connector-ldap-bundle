<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Service;

use Dbp\Relay\CoreBundle\API\UserSessionInterface;
use Dbp\Relay\CoreBundle\Authorization\AuthorizationDataProviderInterface;
use Dbp\Relay\CoreBundle\Helpers\Tools;
use Dbp\Relay\CoreConnectorLdapBundle\DependencyInjection\Configuration;
use Dbp\Relay\CoreConnectorLdapBundle\Event\UserDataLoadedEvent;
use Dbp\Relay\LdapBundle\Common\LdapException;
use Dbp\Relay\LdapBundle\Service\LdapApi;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AuthorizationDataProvider implements AuthorizationDataProviderInterface
{
    private const DEFAULT_VALUE_KEY = 'default';
    private const LDAP_ATTRIBUTE_KEY = 'ldap';
    private const IS_ARRAY_KEY = 'array';

    /** @var LdapApi */
    private $ldapApi;

    /** @var string */
    private $ldapConnectionIdentifier;

    /** @var UserSessionInterface */
    private $userSession;

    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /** @var CacheItemPoolInterface|null */
    private $userCache;

    /** @var array */
    private $availableAttributes;

    public function __construct(LdapApi $ldapApi, UserSessionInterface $userSession, EventDispatcherInterface $eventDispatcher)
    {
        $this->ldapApi = $ldapApi;
        $this->userSession = $userSession;
        $this->eventDispatcher = $eventDispatcher;
        $this->userCache = null;
        $this->availableAttributes = [];
    }

    public function setConfig(array $config)
    {
        $this->loadConfig($config);
    }

    public function setCache(?CacheItemPoolInterface $cachePool)
    {
        $this->userCache = $cachePool;
    }

    public function getAvailableAttributes(): array
    {
        return array_keys($this->availableAttributes);
    }

    /*
     * @throws \RuntimeException
     */
    public function getUserAttributes(?string $userIdentifier): array
    {
        $userAttributes = [];

        if (Tools::isNullOrEmpty($userIdentifier) === false) {
            // in case there is no session, e.g. for debug purposes
            if ($this->userSession->getUserIdentifier() === null || $this->userCache === null) {
                return $this->getUserDataFromLdap($userIdentifier);
            }

            $cacheKey = $this->userSession->getSessionCacheKey().'-'.$userIdentifier;
            $cacheTTL = $this->userSession->getSessionTTL() + 1;

            $userCacheItem = $this->userCache->getItem($cacheKey);
            if ($userCacheItem->isHit()) {
                $userAttributes = $userCacheItem->get();
            } else {
                $userAttributes = $this->getUserDataFromLdap($userIdentifier);
                $userCacheItem->set($userAttributes);
                $userCacheItem->expiresAfter($cacheTTL);
                $this->userCache->save($userCacheItem);
            }
        }

        return $userAttributes;
    }

    /*
     * @throws \RuntimeException
     */
    private function getUserDataFromLdap(string $userIdentifier): array
    {
        try {
            $ldapAttributes = $this->ldapApi->getConnection($this->ldapConnectionIdentifier)->getUserAttributesByIdentifier($userIdentifier);
        } catch (LdapException $exception) {
            throw new \RuntimeException(sprintf('failed to get user data from LDAP: \'%s\'', $exception->getMessage()), 0, $exception);
        }

        $event = new UserDataLoadedEvent($ldapAttributes);
        $this->eventDispatcher->dispatch($event);

        $userAttributes = [];
        foreach ($this->availableAttributes as $attributeName => $attributeData) {
            if (($mappedLdapAttribute = $attributeData[self::LDAP_ATTRIBUTE_KEY] ?? null) !== null &&
                ($attributeValue = $ldapAttributes[$mappedLdapAttribute] ?? null) !== null) {
                if (is_array($attributeValue)) {
                    $attributeValue = $attributeData[self::IS_ARRAY_KEY] ? $attributeValue : $attributeValue[0];
                } else {
                    $attributeValue = $attributeData[self::IS_ARRAY_KEY] ? [$attributeValue] : $attributeValue;
                }
            } else {
                $attributeValue = $event->getUserAttributes()[$attributeName] ?? $attributeData[self::DEFAULT_VALUE_KEY];
            }
            $userAttributes[$attributeName] = $attributeValue;
        }

        return $userAttributes;
    }

    /*
     * @throws \RuntimeException
     */
    private function loadConfig(array $config)
    {
        $this->ldapConnectionIdentifier = $config[Configuration::LDAP_CONNECTION_ATTRIBUTE];

        $this->availableAttributes = [];
        foreach ($config[Configuration::ATTRIBUTES_ATTRIBUTE] as $attribute) {
            $attributeName = $attribute[Configuration::NAME_ATTRIBUTE];
            if (isset($this->availableAttributes[$attributeName])) {
                throw new \RuntimeException(sprintf('multiple declaration of attribute \'%s\'', $attributeName));
            }

            $this->availableAttributes[$attributeName] = [
                self::IS_ARRAY_KEY => $attribute[Configuration::IS_ARRAY_ATTRIBUTE],
                self::DEFAULT_VALUE_KEY => $attribute[Configuration::IS_ARRAY_ATTRIBUTE] ?
                    $attribute[Configuration::DEFAULT_VALUES_ATTRIBUTE] ?? [] :
                    $attribute[Configuration::DEFAULT_VALUE_ATTRIBUTE] ?? null,
                self::LDAP_ATTRIBUTE_KEY => $attribute[Configuration::LDAP_ATTRIBUTE_ATTRIBUTE] ?? null,
                ];
        }
    }
}
