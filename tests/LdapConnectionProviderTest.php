<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Tests;

use Dbp\Relay\CoreConnectorLdapBundle\DependencyInjection\Configuration;
use Dbp\Relay\CoreConnectorLdapBundle\Ldap\LdapConnectionProvider;
use Dbp\Relay\CoreConnectorLdapBundle\Ldap\LdapException;
use PHPUnit\Framework\TestCase;

class LdapConnectionProviderTest extends TestCase
{
    private const TEST_HOST = 'localhost';
    private const TEST_BASE_DN = 'dc=example,dc=com';
    private const TEST_USERNAME = 'user';
    private const TEST_PASSWORD = 'secret';
    private const TEST_OBJECT_CLASS = 'person';
    private const TEST_ENCRYPTION = 'plain';

    private ?LdapConnectionProvider $ldapConnectionProvider = null;

    protected function setUp(): void
    {
        $this->ldapConnectionProvider = new LdapConnectionProvider();
        $this->ldapConnectionProvider->setConfig(self::getTestConfig());
    }

    public function testGetConnection(): void
    {
        $ldapConnection = $this->ldapConnectionProvider->getConnection('connection_1');
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
            $this->ldapConnectionProvider->getConnection('connection_1')->getEntries();
            $this->fail('ldap exception not thrown as expected');
        } catch (LdapException $ldapException) {
            $this->assertEquals(LdapException::SERVER_CONNECTION_FAILED, $ldapException->getCode());
        }
    }

    public static function getTestConfig(): array
    {
        return [
            Configuration::CONNECTIONS_ATTRIBUTE => [
                'connection_1' => [
                    Configuration::LDAP_HOST_ATTRIBUTE => self::TEST_HOST,
                    Configuration::LDAP_BASE_DN_ATTRIBUTE => self::TEST_BASE_DN,
                    Configuration::LDAP_USERNAME_ATTRIBUTE => self::TEST_USERNAME,
                    Configuration::LDAP_PASSWORD_ATTRIBUTE => self::TEST_PASSWORD,
                    Configuration::LDAP_ENCRYPTION_ATTRIBUTE => self::TEST_ENCRYPTION,
                    Configuration::LDAP_OBJECT_CLASS_ATTRIBUTE => self::TEST_OBJECT_CLASS,
                ],
                'connection_2' => [
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
