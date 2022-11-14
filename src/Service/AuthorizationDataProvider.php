<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Service;

use Dbp\Relay\CoreBundle\API\UserSessionInterface;
use Dbp\Relay\CoreBundle\Authorization\AuthorizationDataProviderInterface;
use Dbp\Relay\CoreBundle\Helpers\Tools;
use Dbp\Relay\CoreConnectorLdapBundle\DependencyInjection\Configuration;
use Dbp\Relay\CoreConnectorLdapBundle\Event\UserDataLoadedEvent;
use Dbp\Relay\LdapBundle\Service\LdapApi;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AuthorizationDataProvider implements AuthorizationDataProviderInterface
{
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

    public function getUserAttributes(string $userIdentifier): array
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

    private function getUserDataFromLdap(string $userIdentifier): array
    {
        $userData = $this->ldapApi->getConnection($this->ldapConnectionIdentifier)->getUserAttributesByIdentifier($userIdentifier);

        $event = new UserDataLoadedEvent($userData);
        $this->eventDispatcher->dispatch($event, UserDataLoadedEvent::NAME);

        $userAttributes = [];
        $ldapUserAttributes = $event->getUserAttributes();
        foreach ($this->availableAttributes as $attributeName => $attributeDefaultValue) {
            $userAttributes[$attributeName] = $ldapUserAttributes[$attributeName] ?? $attributeDefaultValue;
        }

        return $userAttributes;
    }

    private function loadConfig(array $config)
    {
        $this->ldapConnectionIdentifier = $config[Configuration::LDAP_CONNECTION_ATTRIBUTE];

        $this->availableAttributes = [];
        foreach ($config[Configuration::ATTRIBUTES_ATTRIBUTE] as $attribute) {
            $attributeName = $attribute[Configuration::NAME_ATTRIBUTE];
            if (isset($this->availableAttributes[$attributeName])) {
                throw new \RuntimeException(sprintf('multiple declaration of attribute \'%s\'', $attributeName));
            }

            $this->availableAttributes[$attributeName] = $attribute[Configuration::IS_ARRAY_ATTRIBUTE] ?
                $attribute[Configuration::DEFAULT_VALUES_ATTRIBUTE] ?? [] :
                $attribute[Configuration::DEFAULT_VALUE_ATTRIBUTE] ?? null;
        }
    }
}
