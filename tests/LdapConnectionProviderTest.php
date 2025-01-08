<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Tests;

use Dbp\Relay\CoreBundle\Rest\Options;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\FilterException;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\FilterTreeBuilder;
use Dbp\Relay\CoreBundle\Rest\Query\Sort\Sort;
use Dbp\Relay\CoreConnectorLdapBundle\DependencyInjection\Configuration;
use Dbp\Relay\CoreConnectorLdapBundle\Ldap\LdapConnection;
use Dbp\Relay\CoreConnectorLdapBundle\Ldap\LdapConnectionInterface;
use Dbp\Relay\CoreConnectorLdapBundle\Ldap\LdapConnectionProvider;
use Dbp\Relay\CoreConnectorLdapBundle\Ldap\LdapException;
use LdapRecord\Testing\ConnectionFake;
use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;
use PHPUnit\Framework\TestCase;

class LdapConnectionProviderTest extends TestCase
{
    public const FAKE_CONNECTION_ID = 'fake_connection_id';

    private const TEST_HOST = 'localhost';
    private const TEST_BASE_DN = 'dc=example,dc=com';
    private const TEST_USERNAME = 'user';
    private const TEST_PASSWORD = 'secret';
    private const TEST_OBJECT_CLASS = 'person';
    private const TEST_ENCRYPTION = 'plain';

    private ?LdapConnectionProvider $ldapConnectionProvider = null;

    public static function createTestLdapConnectionProvider(): LdapConnectionProvider
    {
        $ldapConnectionProvider = new LdapConnectionProvider();
        $ldapConnectionProvider->setConfig(self::getTestConfig());

        return $ldapConnectionProvider;
    }

    public static function mockResults(LdapConnectionProvider $ldapConnectionProvider, array $results = []): void
    {
        self::getFakeLdap($ldapConnectionProvider)
            ->expect(
                LdapFake::operation('search')->andReturn($results)
            );
    }

    private static function getFakeLdap(LdapConnectionProvider $ldapConnectionProvider): LdapFake
    {
        $ldapConnectionProvider->makeFakeConnection(self::FAKE_CONNECTION_ID);
        $connection = $ldapConnectionProvider->getConnection(self::FAKE_CONNECTION_ID);
        assert($connection instanceof LdapConnection);
        $ldapRecordConnection = $connection->getConnection();
        assert($ldapRecordConnection instanceof ConnectionFake);
        $ldapRecordConnection->shouldBeConnected();
        $ldap = $ldapRecordConnection->getLdapConnection();
        assert($ldap instanceof LdapFake);

        return $ldap;
    }

    protected function setUp(): void
    {
        $this->ldapConnectionProvider = self::createTestLdapConnectionProvider();
    }

    protected function tearDown(): void
    {
        DirectoryFake::tearDown();
    }

    public function testGetConnection(): void
    {
        $ldapConnection = $this->ldapConnectionProvider->getConnection(self::FAKE_CONNECTION_ID);
        $this->assertNotNull($ldapConnection);

        $ldapConnection = $this->ldapConnectionProvider->getConnection('connection_2');
        $this->assertNotNull($ldapConnection);
    }

    public function testGetConnectionUndefined(): void
    {
        try {
            $this->ldapConnectionProvider->getConnection('404');
            $this->fail('exception not thrown as expected');
        } catch (LdapException $ldapException) {
            $this->assertEquals(LdapException::LDAP_CONNECTION_UNDEFINED, $ldapException->getCode());
        }
    }

    public function testServerConnectionFailed(): void
    {
        try {
            $this->getFakeConnection()->getEntries();
            $this->fail('ldap exception not thrown as expected');
        } catch (LdapException $ldapException) {
            $this->assertEquals(LdapException::SERVER_CONNECTION_FAILED, $ldapException->getCode());
        }
    }

    public function testGetEntriesEmpty(): void
    {
        $this->expectResults([]);
        $this->assertEmpty($this->getFakeConnection()->getEntries());
    }

    public function testGetEntries(): void
    {
        $this->expectResults([
            [
                'cn' => ['john88'],
                'givenName' => ['John'],
                'sn' => ['Doe'],
                'objectClass' => [self::TEST_OBJECT_CLASS],
            ],
            [
                'cn' => ['janed'],
                'givenName' => ['Jane'],
                'sn' => ['Doelle'],
                'objectClass' => [self::TEST_OBJECT_CLASS],
            ],
            [
                'cn' => ['sm'],
                'givenName' => ['Mario'],
                'sn' => ['Super'],
                'objectClass' => [self::TEST_OBJECT_CLASS],
            ],
        ]);
        $entries = $this->getFakeConnection()->getEntries();
        $this->assertCount(3, $entries);

        $this->assertEquals('john88', $entries[0]->getFirstAttributeValue('cn'));
        $this->assertEquals('John', $entries[0]->getFirstAttributeValue('givenName'));
        $this->assertEquals('Doe', $entries[0]->getFirstAttributeValue('sn'));
        $this->assertEquals(self::TEST_OBJECT_CLASS, $entries[0]->getFirstAttributeValue('objectClass'));

        $this->assertEquals('janed', $entries[1]->getFirstAttributeValue('cn'));
        $this->assertEquals('Jane', $entries[1]->getFirstAttributeValue('givenName'));
        $this->assertEquals('Doelle', $entries[1]->getFirstAttributeValue('sn'));
        $this->assertEquals(self::TEST_OBJECT_CLASS, $entries[1]->getFirstAttributeValue('objectClass'));

        $this->assertEquals('sm', $entries[2]->getFirstAttributeValue('cn'));
        $this->assertEquals('Mario', $entries[2]->getFirstAttributeValue('givenName'));
        $this->assertEquals('Super', $entries[2]->getFirstAttributeValue('sn'));
        $this->assertEquals(self::TEST_OBJECT_CLASS, $entries[2]->getFirstAttributeValue('objectClass'));
    }

    public function testGetEntriesWithSort(): void
    {
        $this->expectResults([
            [
                'cn' => ['janed'],
                'givenName' => ['Jane'],
                'sn' => ['Doelle'],
                'objectClass' => [self::TEST_OBJECT_CLASS],
            ],
            [
                'cn' => ['john88'],
                'givenName' => ['John'],
                'sn' => ['Doe'],
                'objectClass' => [self::TEST_OBJECT_CLASS],
            ],
            [
                'cn' => ['sm'],
                'givenName' => ['Mario'],
                'sn' => ['Super'],
                'objectClass' => [self::TEST_OBJECT_CLASS],
            ],
        ]);

        $options = [];
        Options::setSort($options, new Sort([Sort::createSortField('cn', Sort::DESCENDING_DIRECTION)]));
        $entries = $this->getFakeConnection()->getEntries(options: $options);
        $this->assertCount(3, $entries);

        $this->assertEquals('sm', $entries[0]->getFirstAttributeValue('cn'));
        $this->assertEquals('Mario', $entries[0]->getFirstAttributeValue('givenName'));
        $this->assertEquals('Super', $entries[0]->getFirstAttributeValue('sn'));
        $this->assertEquals(self::TEST_OBJECT_CLASS, $entries[0]->getFirstAttributeValue('objectClass'));

        $this->assertEquals('john88', $entries[1]->getFirstAttributeValue('cn'));
        $this->assertEquals('John', $entries[1]->getFirstAttributeValue('givenName'));
        $this->assertEquals('Doe', $entries[1]->getFirstAttributeValue('sn'));
        $this->assertEquals(self::TEST_OBJECT_CLASS, $entries[1]->getFirstAttributeValue('objectClass'));

        $this->assertEquals('janed', $entries[2]->getFirstAttributeValue('cn'));
        $this->assertEquals('Jane', $entries[2]->getFirstAttributeValue('givenName'));
        $this->assertEquals('Doelle', $entries[2]->getFirstAttributeValue('sn'));
        $this->assertEquals(self::TEST_OBJECT_CLASS, $entries[2]->getFirstAttributeValue('objectClass'));
    }

    /**
     * @throws FilterException
     */
    public function testGetEntriesWithFilter(): void
    {
        $this->expectResults([
            [
                'cn' => ['sm'],
                'givenName' => ['Mario'],
                'sn' => ['Super'],
                'objectClass' => [self::TEST_OBJECT_CLASS],
            ],
        ]);

        $options = [];
        Options::addFilter($options,
            FilterTreeBuilder::create()
                ->iContains('givenName', 'rio')
                ->createFilter());

        $entries = $this->getFakeConnection()->getEntries(options: $options);
        $this->assertCount(1, $entries);
        $this->assertEquals('sm', $entries[0]->getFirstAttributeValue('cn'));
        $this->assertEquals('Mario', $entries[0]->getFirstAttributeValue('givenName'));
        $this->assertEquals('Super', $entries[0]->getFirstAttributeValue('sn'));
        $this->assertEquals(self::TEST_OBJECT_CLASS, $entries[0]->getFirstAttributeValue('objectClass'));
    }

    public function testGetEntriesWithInvalidFilter(): void
    {
        $this->expectResults([]);

        $options = [];
        Options::addFilter($options,
            FilterTreeBuilder::create()
                ->appendChild(new UndefinedConditionNode())
                ->createFilter());

        try {
            $this->getFakeConnection()->getEntries(options: $options);
            $this->fail('exception not thrown as expected');
        } catch (LdapException $ldapException) {
            $this->assertEquals(LdapException::FILTER_INVALID, $ldapException->getCode());
        }

        $options = [];
        Options::addFilter($options,
            FilterTreeBuilder::create()
                ->appendChild(new UndefinedLogicalNode())
                ->createFilter());

        try {
            $this->getFakeConnection()->getEntries(options: $options);
            $this->fail('exception not thrown as expected');
        } catch (LdapException $ldapException) {
            $this->assertEquals(LdapException::FILTER_INVALID, $ldapException->getCode());
        }
    }

    public function testGetEntryNotFound(): void
    {
        $this->expectResults([]);
        try {
            $this->getFakeConnection()->getEntryByAttribute('cn', 'janed');
            $this->fail('exception not thrown as expected');
        } catch (LdapException $ldapException) {
            $this->assertEquals(LdapException::ENTRY_NOT_FOUND, $ldapException->getCode());
        }
    }

    public function testGetEntry(): void
    {
        $this->expectResults([
            [
                'cn' => ['janed'],
                'givenName' => ['Jane'],
                'sn' => ['Doelle'],
                'objectClass' => [self::TEST_OBJECT_CLASS],
            ],
        ]);
        $entry = $this->getFakeConnection()->getEntryByAttribute('cn', 'janed');
        $this->assertEquals('janed', $entry->getFirstAttributeValue('cn'));
        $this->assertEquals('Jane', $entry->getFirstAttributeValue('givenName'));
        $this->assertEquals('Doelle', $entry->getFirstAttributeValue('sn'));
        $this->assertEquals(self::TEST_OBJECT_CLASS, $entry->getFirstAttributeValue('objectClass'));
    }

    private function expectResults(array $results): void
    {
        self::mockResults($this->ldapConnectionProvider, $results);
    }

    private function getFakeConnection(): LdapConnectionInterface
    {
        return $this->ldapConnectionProvider->getConnection(self::FAKE_CONNECTION_ID);
    }

    public static function getTestConfig(): array
    {
        return [
            Configuration::CONNECTIONS_ATTRIBUTE => [
                [
                    Configuration::LDAP_CONNECTION_IDENTIFIER_ATTRIBUTE => self::FAKE_CONNECTION_ID,
                    Configuration::LDAP_HOST_ATTRIBUTE => self::TEST_HOST,
                    Configuration::LDAP_BASE_DN_ATTRIBUTE => self::TEST_BASE_DN,
                    Configuration::LDAP_USERNAME_ATTRIBUTE => self::TEST_USERNAME,
                    Configuration::LDAP_PASSWORD_ATTRIBUTE => self::TEST_PASSWORD,
                    Configuration::LDAP_ENCRYPTION_ATTRIBUTE => self::TEST_ENCRYPTION,
                    Configuration::LDAP_OBJECT_CLASS_ATTRIBUTE => self::TEST_OBJECT_CLASS,
                ],
                [
                    Configuration::LDAP_CONNECTION_IDENTIFIER_ATTRIBUTE => 'connection_2',
                    Configuration::LDAP_HOST_ATTRIBUTE => self::TEST_HOST,
                    Configuration::LDAP_BASE_DN_ATTRIBUTE => self::TEST_BASE_DN,
                    Configuration::LDAP_USERNAME_ATTRIBUTE => self::TEST_USERNAME,
                    Configuration::LDAP_PASSWORD_ATTRIBUTE => self::TEST_PASSWORD,
                    Configuration::LDAP_ENCRYPTION_ATTRIBUTE => self::TEST_ENCRYPTION,
                    Configuration::LDAP_OBJECT_CLASS_ATTRIBUTE => self::TEST_OBJECT_CLASS,
                ],
            ],
        ];
    }
}
