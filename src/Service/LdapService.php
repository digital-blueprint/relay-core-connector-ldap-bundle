<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Service;

use LdapRecord\Connection;
use LdapRecord\LdapInterface;
use LdapRecord\Models\Model;

class LdapService
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var array
     */
    private $providerConfig;

    /**
     * @var array
     */
    private $mappingConfig;

    /**
     * @var string
     */
    private $idAttribute;

    public function __construct()
    {
        $this->mappingConfig = [];
        $this->providerConfig = [];
    }

    public function setLdapConnection(LdapInterface $connection)
    {
        $this->connection->setLdapConnection($connection);
    }

    public function setMappingConfig(array $config)
    {
        $this->mappingConfig = $config;
    }

    public function setLdapConfig(array $config)
    {
        $this->providerConfig = [
            'hosts' => [$config['host'] ?? ''],
            'base_dn' => $config['base_dn'] ?? '',
            'username' => $config['username'] ?? '',
            'password' => $config['password'] ?? '',
        ];

        $encryption = $config['encryption'];
        assert(in_array($encryption, ['start_tls', 'simple_tls'], true));
        $this->providerConfig['use_tls'] = ($encryption === 'start_tls');
        $this->providerConfig['use_ssl'] = ($encryption === 'simple_tls');
        $this->providerConfig['port'] = ($encryption === 'start_tls') ? 389 : 636;

        $this->idAttribute = $config['id_attribute'] ?? '';

        $this->connection = new Connection($this->providerConfig);
    }

    public function getUser(string $identifier): ?Model
    {
        assert($this->connection !== null);
        $builder = $this->connection->query();

        return $builder->where('objectClass', '=', 'person')
            ->whereEquals($this->idAttribute, $identifier)
            ->first();
    }

    public function getAttribute(string $name)
    {
        if (!key_exists($name, $this->mappingConfig)) {
            return null;
        }

        return $this->mappingConfig[$name];
    }
}
