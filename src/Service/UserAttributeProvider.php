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
        private readonly UserSessionInterface $userSession)
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

        if ($userIdentifier === null) {
            $value = $this->availableAttributes[$name][self::DEFAULT_VALUE_KEY];
        } else {
            $userCacheItem = null;
            if ($this->userSession->isAuthenticated() // non-authenticated is allowed for debug command
                && ($userCacheItem = $this->userCache?->getItem($this->userSession->getSessionCacheKey().'-'.$userIdentifier))
                && $userCacheItem->isHit()) {
                $userAttributes = $userCacheItem->get();
            } else {
                $userAttributes = $this->getUserDataFromLdap($userIdentifier);
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
    private function getUserDataFromLdap(string $userIdentifier): array
    {
        $ldapEntry = null;
        try {
            $ldapEntry = $this->ldapConnectionProvider->getConnection($this->ldapConnectionIdentifier)
                ->getEntryByAttribute($this->ldapUserIdentifierAttribute, $userIdentifier);
        } catch (LdapException $exception) {
            if ($exception->getCode() !== LdapException::ENTRY_NOT_FOUND) {
                throw match ($exception->getCode()) {
                    LdapException::SERVER_CONNECTION_FAILED => ApiError::withDetails(Response::HTTP_BAD_GATEWAY,
                        'failed to connect to LDAP server'),
                    default => new \RuntimeException(
                        sprintf('failed to get user data from LDAP for user \'%s\': \'%s\' (code: %d)',
                            $userIdentifier, $exception->getMessage(), $exception->getCode())),
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
        $this->ldapConnectionIdentifier = $config[Configuration::LDAP_CONNECTION_ATTRIBUTE];
        $this->ldapUserIdentifierAttribute = $config[Configuration::LDAP_USER_IDENTIFIER_ATTRIBUTE_ATTRIBUTE] ?? 'cn';

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
