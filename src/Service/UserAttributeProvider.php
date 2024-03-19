<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Service;

use Dbp\Relay\CoreBundle\API\UserSessionInterface;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\Helpers\Tools;
use Dbp\Relay\CoreBundle\User\UserAttributeProviderInterface;
use Dbp\Relay\CoreConnectorLdapBundle\DependencyInjection\Configuration;
use Dbp\Relay\CoreConnectorLdapBundle\Event\UserDataLoadedEvent;
use Dbp\Relay\CoreConnectorLdapBundle\Ldap\LdapConnectionProvider;
use Dbp\Relay\CoreConnectorLdapBundle\Ldap\LdapException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;

class UserAttributeProvider implements UserAttributeProviderInterface
{
    private const DEFAULT_VALUE_KEY = 'default';
    private const LDAP_ATTRIBUTE_KEY = 'ldap';
    private const IS_ARRAY_KEY = 'array';

    private LdapConnectionProvider $ldapConnectionProvider;

    private string $ldapConnectionIdentifier;

    private UserSessionInterface $userSession;

    private EventDispatcherInterface $eventDispatcher;

    private ?CacheItemPoolInterface $userCache;

    /** @var array[] */
    private array $availableAttributes;

    public function __construct(LdapConnectionProvider $ldapConnectionProvider, UserSessionInterface $userSession, EventDispatcherInterface $eventDispatcher)
    {
        $this->ldapConnectionProvider = $ldapConnectionProvider;
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
        if (Tools::isNullOrEmpty($userIdentifier) === false) {
            // in case there is no session, e.g. for debug purposes
            if ($this->userSession->getUserIdentifier() === null || $this->userCache === null) {
                return $this->getUserDataFromLdap($userIdentifier);
            }

            $userCacheItem = $this->userCache->getItem($this->userSession->getSessionCacheKey().'-'.$userIdentifier);
            if ($userCacheItem->isHit()) {
                $userAttributes = $userCacheItem->get();
            } else {
                $userAttributes = $this->getUserDataFromLdap($userIdentifier);
                $userCacheItem->set($userAttributes);
                $userCacheItem->expiresAfter($this->userSession->getSessionTTL() + 1);
                $this->userCache->save($userCacheItem);
            }
        } else {
            $userAttributes = array_map(function ($attributeData) {
                return $attributeData[self::DEFAULT_VALUE_KEY];
            }, $this->availableAttributes);
        }

        return $userAttributes;
    }

    /*
     * @throws \RuntimeException
     */
    private function getUserDataFromLdap(string $userIdentifier): array
    {
        try {
            $ldapAttributes = $this->ldapConnectionProvider->getConnection($this->ldapConnectionIdentifier)->getUserAttributesByIdentifier($userIdentifier);
        } catch (LdapException $exception) {
            throw ApiError::withDetails(Response::HTTP_BAD_GATEWAY, sprintf('failed to get user data from LDAP: \'%s\'', $exception->getMessage()));
        }

        $event = new UserDataLoadedEvent($ldapAttributes);
        $this->eventDispatcher->dispatch($event);

        $userAttributes = [];
        foreach ($this->availableAttributes as $attributeName => $attributeData) {
            if (($mappedLdapAttribute = $attributeData[self::LDAP_ATTRIBUTE_KEY] ?? null) !== null
                && ($attributeValue = $ldapAttributes[$mappedLdapAttribute] ?? null) !== null) {
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
