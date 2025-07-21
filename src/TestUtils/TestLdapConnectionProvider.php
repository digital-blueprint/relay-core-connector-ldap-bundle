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
use Symfony\Component\DependencyInjection\Container as ServiceContainer;

class TestLdapConnectionProvider extends LdapConnectionProvider
{
    public const DEFAULT_CONNECTION_IDENTIFIER = 'default_connection_id';

    public const DEFAULT_CONFIG = [
        Configuration::CONNECTIONS_ATTRIBUTE => [
            self::DEFAULT_CONNECTION_IDENTIFIER => [
                Configuration::LDAP_HOST_ATTRIBUTE => 'ldap.com',
                Configuration::LDAP_BASE_DN_ATTRIBUTE => self::BASE_DN,
                Configuration::LDAP_USERNAME_ATTRIBUTE => 'user',
                Configuration::LDAP_PASSWORD_ATTRIBUTE => 'secret',
                Configuration::LDAP_ENCRYPTION_ATTRIBUTE => 'start_tls',
                Configuration::LDAP_OBJECT_CLASS_ATTRIBUTE => self::OBJECT_CLASS,
                Configuration::LDAP_NUM_RESULT_ITEMS_WILL_SORT_LIMIT_ATTRIBUTE => 3,
            ],
        ],
    ];

    private const BASE_DN = 'dc=example,dc=com';
    private const OBJECT_CLASS = 'person';

    public static function create(): TestLdapConnectionProvider
    {
        Container::getInstance()->flush();

        $provider = new TestLdapConnectionProvider();
        $provider->setConfig(self::DEFAULT_CONFIG);

        return $provider;
    }

    /**
     * values used in exceptions need to be converted to the internal query representation.
     */
    public static function toExpectedValue(string $input): string
    {
        $output = '';
        foreach (str_split($input) as $char) {
            $output .= '\\'.dechex(ord($char));
        }

        return $output;
    }

    public static function getObjectClassCriteria(): string
    {
        return 'objectClass='.self::toExpectedValue(self::OBJECT_CLASS);
    }

    public function useInApiTest(ServiceContainer $container): void
    {
        $container->set(LdapConnectionProvider::class, $this);
    }

    /**
     * @param callable(string): bool|null $isQueryAsExpected
     */
    public function mockResults(array $results = [],
        ?string $expectCn = null, ?callable $isQueryAsExpected = null,
        string $mockConnectionIdentifier = self::DEFAULT_CONNECTION_IDENTIFIER): void
    {
        $ldapExpectation = LdapFake::operation('search')
            ->once()
            ->andReturn($results);

        if ($expectCn !== null) {
            $isQueryAsExpected = function (string $query) use ($expectCn): bool {
                $expectCnHex = self::toExpectedValue($expectCn);
                $objectClassCriteria = self::getObjectClassCriteria();

                return $query === "(&($objectClassCriteria)(cn=$expectCnHex))";
            };
        }
        if ($isQueryAsExpected !== null) {
            $ldapExpectation->with(self::BASE_DN, $isQueryAsExpected);
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
}
