<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Service;

use Dbp\Relay\CoreBundle\API\UserSessionInterface;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\User\UserAttributeException;
use Dbp\Relay\CoreBundle\User\UserAttributeProviderInterface;
use Dbp\Relay\CoreConnectorLdapBundle\DependencyInjection\Configuration;
use Dbp\Relay\CoreConnectorLdapBundle\Ldap\LdapConnectionProvider;
use Dbp\Relay\CoreConnectorLdapBundle\Ldap\LdapException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\Response;

class UserAttributeProvider implements UserAttributeProviderInterface
{
    private const DEFAULT_VALUE_KEY = 'default';
    private const LDAP_ATTRIBUTE_KEY = 'ldap';
    private const IS_ARRAY_KEY = 'array';

    private ?CacheItemPoolInterface $userCache = null;

    /** @var array[] */
    private array $availableAttributes = [];

    private ?string $ldapConnectionIdentifier = null;
    private ?string $ldapUserIdentifierAttribute = null;

    public function __construct(
        private readonly LdapConnectionProvider $ldapConnectionProvider,
        private readonly UserSessionInterface $userSession,
        private readonly AuthorizationService $authorizationService)
    {
    }

    public function setConfig(array $config): void
    {
        $this->loadConfig($config);
    }

    public function setCache(?CacheItemPoolInterface $cachePool): void
    {
        $this->userCache = $cachePool;
    }

    public function hasUserAttribute(string $name): bool
    {
        return array_key_exists($name, $this->availableAttributes);
    }

    public function getUserAttribute(?string $userIdentifier, string $name): mixed
    {
        if (false === array_key_exists($name, $this->availableAttributes)) {
            throw new UserAttributeException("user attribute '$name' undefined", UserAttributeException::USER_ATTRIBUTE_UNDEFINED);
        }

        $currentLdapUserIdentifier = $this->authorizationService->getCurrentLdapUserIdentifier();
        if ($currentLdapUserIdentifier === null) {
            $value = $this->availableAttributes[$name][self::DEFAULT_VALUE_KEY];
        } else {
            $userCacheItem = null;
            if ($this->userSession->isAuthenticated() // non-authenticated is allowed for debug command
                && ($userCacheItem = $this->userCache?->getItem($this->userSession->getSessionCacheKey().'-'.$currentLdapUserIdentifier))
                && $userCacheItem->isHit()) {
                $userAttributes = $userCacheItem->get();
            } else {
                $userAttributes = $this->getUserDataFromLdap($currentLdapUserIdentifier);
                if ($userCacheItem) {
                    $userCacheItem->set($userAttributes);
                    $userCacheItem->expiresAfter($this->userSession->getSessionTTL() + 1);
                    $this->userCache->save($userCacheItem);
                }
            }
            $value = $userAttributes[$name];
        }

        return $value;
    }

    /*
     * @throws ApiError
     */
    private function getUserDataFromLdap(string $ldapUserIdentifier): array
    {
        $ldapEntry = null;
        try {
            $ldapEntry = $this->ldapConnectionProvider->getConnection($this->ldapConnectionIdentifier)
                ->getEntryByAttribute($this->ldapUserIdentifierAttribute, $ldapUserIdentifier);
        } catch (LdapException $exception) {
            if ($exception->getCode() !== LdapException::ENTRY_NOT_FOUND) {
                throw match ($exception->getCode()) {
                    LdapException::SERVER_CONNECTION_FAILED => ApiError::withDetails(Response::HTTP_BAD_GATEWAY,
                        'failed to connect to LDAP server'),
                    default => new \RuntimeException(
                        sprintf('failed to get user data from LDAP for user \'%s\': \'%s\' (code: %d)',
                            $ldapUserIdentifier, $exception->getMessage(), $exception->getCode())),
                };
            }
        }

        $userAttributes = [];
        foreach ($this->availableAttributes as $attributeName => $attributeData) {
            if (($mappedLdapAttribute = $attributeData[self::LDAP_ATTRIBUTE_KEY] ?? null) !== null
                && ($attributeValue = $ldapEntry?->getAttributeValue($mappedLdapAttribute, null)) !== null) {
                if (is_array($attributeValue)) {
                    $attributeValue = $attributeData[self::IS_ARRAY_KEY] ? $attributeValue : $attributeValue[0];
                } else {
                    $attributeValue = $attributeData[self::IS_ARRAY_KEY] ? [$attributeValue] : $attributeValue;
                }
            } else {
                $attributeValue = $attributeData[self::DEFAULT_VALUE_KEY];
            }
            $userAttributes[$attributeName] = $attributeValue;
        }

        return $userAttributes;
    }

    /*
     * @throws \RuntimeException
     */
    private function loadConfig(array $config): void
    {
        $this->ldapConnectionIdentifier = $config[Configuration::USER_ATTRIBUTE_LDAP_CONNECTION_ATTRIBUTE];
        $this->ldapUserIdentifierAttribute = $config[Configuration::USER_ATTRIBUTE_LDAP_USER_IDENTIFIER_ATTRIBUTE_ATTRIBUTE] ?? 'cn';

        $this->availableAttributes = [];
        foreach ($config[Configuration::USER_ATTRIBUTE_ATTRIBUTES_ATTRIBUTE] as $attribute) {
            $attributeName = $attribute[Configuration::USER_ATTRIBUTE_NAME_ATTRIBUTE];
            if (isset($this->availableAttributes[$attributeName])) {
                throw new \RuntimeException(sprintf('multiple declaration of attribute \'%s\'', $attributeName));
            }

            $this->availableAttributes[$attributeName] = [
                self::IS_ARRAY_KEY => $attribute[Configuration::USER_ATTRIBUTE_IS_ARRAY_ATTRIBUTE],
                self::DEFAULT_VALUE_KEY => $attribute[Configuration::USER_ATTRIBUTE_IS_ARRAY_ATTRIBUTE] ?
                    $attribute[Configuration::USER_ATTRIBUTE_DEFAULT_VALUES_ATTRIBUTE] ?? [] :
                    $attribute[Configuration::USER_ATTRIBUTE_DEFAULT_VALUE_ATTRIBUTE] ?? null,
                self::LDAP_ATTRIBUTE_KEY => $attribute[Configuration::USER_ATTRIBUTE_LDAP_ATTRIBUTE_ATTRIBUTE] ?? null,
            ];
        }
    }
}
