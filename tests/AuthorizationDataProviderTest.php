<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Tests;

use Dbp\Relay\CoreBundle\TestUtils\TestUserSession;
use Dbp\Relay\CoreConnectorLdapBundle\DependencyInjection\Configuration;
use Dbp\Relay\CoreConnectorLdapBundle\Service\AuthorizationDataProvider;
use Dbp\Relay\LdapBundle\Service\LdapApi;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class AuthorizationDataProviderTest extends TestCase
{
    private const TEST_CONNECTION_IDENTIFIER = 'TEST_CONNECTION';

    public const ROLES_ATTRIBUTE = 'roles';
    public const MISC_ATTRIBUTE = 'misc';
    public const MISC_ARRAY_ATTRIBUTE = 'misc_array';

    public const LDAP_IDENTIFIER_ATTRIBUTE_NAME = 'ID';
    public const LDAP_ROLES_ATTRIBUTE_NAME = 'LDAP-ROLES';

    /** @var AuthorizationDataProvider */
    private $authzDataProvider;

    /** @var EventDispatcher */
    private $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $testUsers = [];
        $this->addTestUser($testUsers, 'money82', ['VIEWER', 'EDITOR']);
        $this->addTestUser($testUsers, 'penny80', ['VIEWER']);
        $this->addTestUser($testUsers, 'sunny85', []);
        $this->addTestUser($testUsers, 'honey90', null);

        $ldapApi = new LdapApi();
        $ldapApi->addTestConnection(self::TEST_CONNECTION_IDENTIFIER, [
            'encryption' => 'simple_tls',
            'attributes' => ['identifier' => self::LDAP_IDENTIFIER_ATTRIBUTE_NAME],
            ], $testUsers);

        $this->eventDispatcher = new EventDispatcher();

        $this->authzDataProvider = new AuthorizationDataProvider($ldapApi, new TestUserSession('user'), $this->eventDispatcher);
        $this->authzDataProvider->setConfig($this->createConfig());
    }

    private function addTestUser(array &$testUsers, string $identifier, ?array $roles)
    {
        $testUser = [];
        $testUser[self::LDAP_IDENTIFIER_ATTRIBUTE_NAME] = [$identifier];
        if ($roles !== null) {
            $testUser[self::LDAP_ROLES_ATTRIBUTE_NAME] = $roles;
        }

        $testUsers[] = $testUser;
    }

    private function createConfig(): array
    {
        $config = [];
        $config[Configuration::LDAP_CONNECTION_ATTRIBUTE] = self::TEST_CONNECTION_IDENTIFIER;
        $config[Configuration::ATTRIBUTES_ATTRIBUTE] = [
            [
                Configuration::NAME_ATTRIBUTE => self::ROLES_ATTRIBUTE,
                Configuration::IS_ARRAY_ATTRIBUTE => true,
                Configuration::LDAP_ATTRIBUTE_ATTRIBUTE => self::LDAP_ROLES_ATTRIBUTE_NAME,
                Configuration::DEFAULT_VALUES_ATTRIBUTE => ['DEFAULT'],
            ],
            [
                Configuration::NAME_ATTRIBUTE => self::MISC_ATTRIBUTE,
                Configuration::IS_ARRAY_ATTRIBUTE => false,
                Configuration::DEFAULT_VALUE_ATTRIBUTE => 0,
            ],
            [
                Configuration::NAME_ATTRIBUTE => self::MISC_ARRAY_ATTRIBUTE,
                Configuration::IS_ARRAY_ATTRIBUTE => true,
                Configuration::DEFAULT_VALUES_ATTRIBUTE => [1, 2, 3],
            ],
        ];

        return $config;
    }

    public function testAttributeMapping()
    {
        $authzUserAttributes = $this->authzDataProvider->getUserAttributes('money82');

        $this->assertCount(3, $authzUserAttributes);
        $this->assertArrayHasKey(self::ROLES_ATTRIBUTE, $authzUserAttributes);
        $roles = $authzUserAttributes[self::ROLES_ATTRIBUTE];
        $this->assertCount(2, $roles);
        $this->assertContains('VIEWER', $roles);
        $this->assertContains('EDITOR', $roles);

        $authzUserAttributes = $this->authzDataProvider->getUserAttributes('penny80');

        $this->assertCount(3, $authzUserAttributes);
        $this->assertArrayHasKey(self::ROLES_ATTRIBUTE, $authzUserAttributes);
        $roles = $authzUserAttributes[self::ROLES_ATTRIBUTE];
        $this->assertCount(1, $roles);
        $this->assertContains('VIEWER', $roles);

        $authzUserAttributes = $this->authzDataProvider->getUserAttributes('sunny85');

        $this->assertCount(3, $authzUserAttributes);
        $this->assertArrayHasKey(self::ROLES_ATTRIBUTE, $authzUserAttributes);
        $roles = $authzUserAttributes[self::ROLES_ATTRIBUTE];
        $this->assertEmpty($roles);
    }

    public function testUserDataLoadedEvent()
    {
        // event subscriber writes the number of LDAP roles into self::MISC_ATTRIBUTE
        $this->eventDispatcher->addSubscriber(new UserDataLoadedTestEventSubcriber());

        $authzUserAttributes = $this->authzDataProvider->getUserAttributes('money82');

        $this->assertCount(3, $authzUserAttributes);
        $this->assertArrayHasKey(self::MISC_ATTRIBUTE, $authzUserAttributes);
        $misc = $authzUserAttributes[self::MISC_ATTRIBUTE];
        $this->assertEquals(2, $misc);

        $authzUserAttributes = $this->authzDataProvider->getUserAttributes('penny80');

        $this->assertCount(3, $authzUserAttributes);
        $this->assertArrayHasKey(self::MISC_ATTRIBUTE, $authzUserAttributes);
        $misc = $authzUserAttributes[self::MISC_ATTRIBUTE];
        $this->assertEquals(1, $misc);
    }

    public function testDefaultValueLdapAttributeNotFound()
    {
        $authzUserAttributes = $this->authzDataProvider->getUserAttributes('honey90');

        $this->assertCount(3, $authzUserAttributes);
        $this->assertArrayHasKey(self::ROLES_ATTRIBUTE, $authzUserAttributes);
        $roles = $authzUserAttributes[self::ROLES_ATTRIBUTE];
        $this->assertCount(1, $roles);
        $this->assertContains('DEFAULT', $roles);
    }

    public function testDefaultValueLdapAttributeNotMapped()
    {
        $authzUserAttributes = $this->authzDataProvider->getUserAttributes('money82');

        $this->assertCount(3, $authzUserAttributes);
        $this->assertArrayHasKey(self::MISC_ATTRIBUTE, $authzUserAttributes);
        $misc = $authzUserAttributes[self::MISC_ATTRIBUTE];
        $this->assertEquals(0, $misc);

        $this->assertArrayHasKey(self::MISC_ARRAY_ATTRIBUTE, $authzUserAttributes);
        $miscArray = $authzUserAttributes[self::MISC_ARRAY_ATTRIBUTE];
        $this->assertEquals([1, 2, 3], $miscArray);
    }

    public function testWithoutUserId()
    {
        $this->assertEquals($this->authzDataProvider->getUserAttributes(null),
            [
            self::ROLES_ATTRIBUTE => ['DEFAULT'],
            self::MISC_ATTRIBUTE => 0,
            self::MISC_ARRAY_ATTRIBUTE => [1, 2, 3],
            ]);
    }

    public function testUserNotFoundException()
    {
        $this->expectException(\RuntimeException::class);
        $this->authzDataProvider->getUserAttributes('not_found');
    }

    public function testMultipleAttributeDeclarationsException()
    {
        $config = $this->createConfig();
        // add duplicate attribute entry
        $config[Configuration::ATTRIBUTES_ATTRIBUTE][] = [
                Configuration::NAME_ATTRIBUTE => self::ROLES_ATTRIBUTE,
                Configuration::IS_ARRAY_ATTRIBUTE => true,
                Configuration::LDAP_ATTRIBUTE_ATTRIBUTE => self::LDAP_ROLES_ATTRIBUTE_NAME,
            ];
        $this->expectException(\RuntimeException::class);
        $this->authzDataProvider->setConfig($config);
    }
}
