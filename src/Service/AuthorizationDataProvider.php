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

    /** @var string[] */
    private $availableRoles;

    /** @var string[] */
    private $availableAttributes;

    public function __construct(LdapApi $ldapApi, UserSessionInterface $userSession, EventDispatcherInterface $eventDispatcher)
    {
        $this->ldapApi = $ldapApi;
        $this->userSession = $userSession;
        $this->eventDispatcher = $eventDispatcher;
        $this->userCache = null;

        $this->availableRoles = [];
        $this->availableAttributes = [];
    }

    public function setConfig(array $config)
    {
        $this->ldapConnectionIdentifier = $config[Configuration::LDAP_CONNECTION_ATTRIBUTE];

        foreach ($config[Configuration::ROLES_ATTRIBUTE] as $role) {
            $this->availableRoles[] = $role[Configuration::NAME_ATTRIBUTE];
        }

        foreach ($config[Configuration::ATTRIBUTES_ATTRIBUTE] as $attribute) {
            $this->availableAttributes[] = $attribute[Configuration::NAME_ATTRIBUTE];
        }
    }

    public function setCache(?CacheItemPoolInterface $cachePool)
    {
        $this->userCache = $cachePool;
    }

    public function getAvailableRoles(): array
    {
        return $this->availableRoles;
    }

    public function getAvailableAttributes(): array
    {
        return $this->availableAttributes;
    }

    public function getUserData(string $userId, array &$userRoles, array &$userAttributes): void
    {
        if (Tools::isNullOrEmpty($userId) === false) {
            $cacheKey = $this->userSession->getSessionCacheKey().'-'.$userId;
            $cacheTTL = $this->userSession->getSessionTTL() + 1;

            $userCacheItem = $this->userCache->getItem($cacheKey);
            if ($userCacheItem->isHit()) {
                /** @var UserAuthorizationData */
                $userAuthorizationData = $userCacheItem->get();
                $userRoles = $userAuthorizationData->roles;
                $userAttributes = $userAuthorizationData->attributes;
            } else {
                $this->getUserDataFromLdap($userId, $userRoles, $userAttributes);

                $userAuthorizationData = new UserAuthorizationData();
                $userAuthorizationData->roles = $userRoles;
                $userAuthorizationData->attributes = $userAttributes;

                $userCacheItem->set($userAuthorizationData);
                $userCacheItem->expiresAfter($cacheTTL);
                $this->userCache->save($userCacheItem);
            }
        }
    }

    private function getUserDataFromLdap(string $userId, array &$userRoles, array &$userAttributes): void
    {
        $userData = $this->ldapApi->getConnection($this->ldapConnectionIdentifier)->getUserAttributesByIdentifier($userId);

        $event = new UserDataLoadedEvent($userData);
        $this->eventDispatcher->dispatch($event, UserDataLoadedEvent::NAME);

        $userRoles = $event->getUserRoles();
        $userAttributes = $event->getUserAttributes();
    }
}
