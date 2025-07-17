<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\TestUtils;

use Dbp\Relay\CoreConnectorLdapBundle\DependencyInjection\Configuration;
use Dbp\Relay\CoreConnectorLdapBundle\Ldap\LdapConnection;
use Dbp\Relay\CoreConnectorLdapBundle\Ldap\LdapConnectionInterface;
use Dbp\Relay\CoreConnectorLdapBundle\Ldap\LdapConnectionProvider;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Testing\ConnectionFake;
use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;

class TestLdapConnectionProvider extends LdapConnectionProvider
{
    public const DEFAULT_CONNECTION_IDENTIFIER = 'default_connection_id';

    public const DEFAULT_CONFIG = [
        Configuration::CONNECTIONS_ATTRIBUTE => [
            self::DEFAULT_CONNECTION_IDENTIFIER => [
                Configuration::LDAP_HOST_ATTRIBUTE => 'ldap.com',
                Configuration::LDAP_BASE_DN_ATTRIBUTE => 'dc=example,dc=com',
                Configuration::LDAP_USERNAME_ATTRIBUTE => 'user',
                Configuration::LDAP_PASSWORD_ATTRIBUTE => 'secret',
                Configuration::LDAP_ENCRYPTION_ATTRIBUTE => 'start_tls',
                Configuration::LDAP_OBJECT_CLASS_ATTRIBUTE => 'person',
                Configuration::LDAP_NUM_RESULT_ITEMS_WILL_SORT_LIMIT_ATTRIBUTE => 3,
            ],
        ],
    ];

    public static function create(): TestLdapConnectionProvider
    {
        Container::getInstance()->flush();

        $provider = new TestLdapConnectionProvider();
        $provider->setConfig(self::DEFAULT_CONFIG);

        return $provider;
    }

    public function useInApiTest(\Symfony\Component\DependencyInjection\Container $container): void
    {
        $container->set(LdapConnectionProvider::class, $this);
    }

    public function mockResults(array $results = [], ?string $expectCn = null, string $expectObjectClass = 'person',
        string $mockConnectionIdentifier = self::DEFAULT_CONNECTION_IDENTIFIER): void
    {
        $ldapExpectation = LdapFake::operation('search')
            ->once()
            ->andReturn($results);

        if ($expectCn !== null) {
            $expectCnOctal = self::convertStringToOctal($expectCn);
            $expectObjectClassOctal = self::convertStringToOctal($expectObjectClass);
            $expectQuery = "(&(objectClass=$expectObjectClassOctal)(cn=$expectCnOctal))";
            $ldapExpectation->with('dc=example,dc=com', $expectQuery);
        }

        $this->getFakeLdap($mockConnectionIdentifier)
            ->expect($ldapExpectation);
    }

    public function expectConnection(string $mockConnectionIdentifier = self::DEFAULT_CONNECTION_IDENTIFIER): void
    {
        $this->getFakeLdap($mockConnectionIdentifier);
    }

    public function getConnection(string $connectionIdentifier): LdapConnectionInterface
    {
        try {
            $connection = parent::getConnection($connectionIdentifier);
            assert($connection instanceof LdapConnection);

            if (false === Container::getInstance()->hasConnection($connectionIdentifier)) {
                Container::getInstance()->addConnection(
                    new Connection(LdapConnection::toLdapRecordConnectionConfig($connection->getConnectionConfig())),
                    $connectionIdentifier);

                $connectionFake = DirectoryFake::setup($connectionIdentifier);
                $connectionFake->actingAs('cn=admin,dc=local,dc=com');
                $connectionFake->shouldNotBeConnected();
                $connection->setMockConnection($connectionFake);
            }

            return $connection;
        } catch (\Exception $exception) {
            throw new \RuntimeException('creating mock connection failed: '.$exception->getMessage());
        }
    }

    public function tearDown(): void
    {
        DirectoryFake::tearDown();
    }

    private function getFakeLdap(string $mockConnectionIdentifier): LdapFake
    {
        $connection = $this->getConnection($mockConnectionIdentifier);
        assert($connection instanceof LdapConnection);
        $ldapRecordConnection = $connection->getConnection();
        assert($ldapRecordConnection instanceof ConnectionFake);
        $ldapRecordConnection->shouldBeConnected();
        $ldap = $ldapRecordConnection->getLdapConnection();
        assert($ldap instanceof LdapFake);

        return $ldap;
    }

    private static function convertStringToOctal(string $input): string
    {
        $output = '';
        foreach (str_split($input) as $char) {
            $output .= '\\'.dechex(ord($char));
        }

        return $output;
    }
}
