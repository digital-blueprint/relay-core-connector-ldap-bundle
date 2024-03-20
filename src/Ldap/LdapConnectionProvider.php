<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Ldap;

use Dbp\Relay\CoreConnectorLdapBundle\DependencyInjection\Configuration;
use Psr\Cache\CacheItemPoolInterface;

class LdapConnectionProvider
{
    private array $connectionConfigs = [];

    /** @var CacheItemPoolInterface[] */
    private array $cachePools = [];

    /** @var LdapConnectionInterface[] */
    private array $connections = [];

    public function setConfig(array $config)
    {
        $this->connectionConfigs = [];
        foreach ($config[Configuration::CONNECTIONS_ATTRIBUTE] as $connection) {
            $this->connectionConfigs[$connection[Configuration::LDAP_CONNECTION_IDENTIFIER_ATTRIBUTE]] = $connection;
        }
    }

    /**
     * @param CacheItemPoolInterface[] $cachePools
     */
    public function setCaches(array $cachePools): void
    {
        $this->cachePools = $cachePools;
    }

    public function getConnection(string $connectionIdentifier): LdapConnectionInterface
    {
        $connection = $this->connections[$connectionIdentifier] ?? null;
        if ($connection === null) {
            $connectionConfig = $this->connectionConfigs[$connectionIdentifier] ?? null;
            if ($connectionConfig === null) {
                throw new LdapException(sprintf('LDAP connection \'%s\' undefined', $connectionIdentifier), LdapException::LDAP_CONNECTION_UNDEFINED);
            }
            $cachePool = $this->cachePools[$connectionIdentifier] ?? null;
            $connection = new LdapConnection($connectionConfig, $cachePool, $cachePool !== null ? $connectionConfig[Configuration::LDAP_CACHE_TTL_ATTRIBUTE] : 0);
            $this->connections[$connectionIdentifier] = $connection;
        }

        return $connection;
    }

    public function createConnection(string $host, string $base_dn, string $username, string $password,
        string $encryption = 'start_tls', string $objectClass = 'person', string $identifierAttribute = 'cn'): LdapConnectionInterface
    {
        $connectionConfig = [
            Configuration::LDAP_HOST_ATTRIBUTE => $host,
            Configuration::LDAP_BASE_DN_ATTRIBUTE => $base_dn,
            Configuration::LDAP_USERNAME_ATTRIBUTE => $username,
            Configuration::LDAP_PASSWORD_ATTRIBUTE => $password,
            Configuration::LDAP_ENCRYPTION_ATTRIBUTE => $encryption,
            Configuration::LDAP_OBJECT_CLASS_ATTRIBUTE => $objectClass,
            Configuration::LDAP_IDENTIFIER_ATTRIBUTE_ATTRIBUTE => $identifierAttribute,
        ];

        return new LdapConnection($connectionConfig);
    }

    public function addTestConnection(string $connectionIdentifier, array $config = [], array $testUsers = [])
    {
        $this->connections[$connectionIdentifier] = new TestLdapConnection($config, $testUsers);
    }

    public function getConnectionIdentifiers(): array
    {
        return array_keys($this->connectionConfigs);
    }
}
