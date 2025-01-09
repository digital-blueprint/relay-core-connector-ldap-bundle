<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Tests;

use Dbp\Relay\CoreBundle\TestUtils\TestUserSession;
use Dbp\Relay\CoreConnectorLdapBundle\DependencyInjection\Configuration;
use Dbp\Relay\CoreConnectorLdapBundle\Ldap\LdapConnectionProvider;
use Dbp\Relay\CoreConnectorLdapBundle\Service\UserAttributeProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class UserAttributeProviderTest extends TestCase
{
    public const ROLES_ATTRIBUTE = 'roles';
    public const MISC_ATTRIBUTE = 'misc';
    public const MISC_ARRAY_ATTRIBUTE = 'misc_array';

    public const LDAP_IDENTIFIER_ATTRIBUTE_NAME = 'cn';
    public const LDAP_ROLES_ATTRIBUTE_NAME = 'LDAP-ROLES';

    private UserAttributeProvider $userAttributeProvider;
    private LdapConnectionProvider $ldapConnectionProvider;

    protected function setUp(): void
    {
        $this->ldapConnectionProvider = LdapConnectionProviderTest::createTestLdapConnectionProvider();
    }

    private function setupUserAttributeProviderWithUser(string $userIdentifier = 'testuser', bool $isAuthenticated = true, bool $isServiceAccount = false): void
    {
        $this->userAttributeProvider = new UserAttributeProvider(
            $this->ldapConnectionProvider, new TestUserSession(
                $userIdentifier, isAuthenticated: $isAuthenticated, isServiceAccount: $isServiceAccount));
        $this->userAttributeProvider->setConfig($this->createConfig()[Configuration::USER_ATTRIBUTE_PROVIDER_ATTRIBUTE]);
    }

    private function createConfig(): array
    {
        return [
            Configuration::USER_ATTRIBUTE_PROVIDER_ATTRIBUTE => [
                Configuration::LDAP_CONNECTION_ATTRIBUTE => LdapConnectionProviderTest::FAKE_CONNECTION_ID,
                Configuration::LDAP_USER_IDENTIFIER_ATTRIBUTE_ATTRIBUTE => self::LDAP_IDENTIFIER_ATTRIBUTE_NAME,
                Configuration::ATTRIBUTES_ATTRIBUTE => [
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
                ],
            ],
        ];
    }

    public function testAttributeMapping()
    {
        $this->setupUserAttributeProviderWithUser('money82');
        $this->mockResultsFor('money82');
        $authzUserAttributes = $this->userAttributeProvider->getUserAttributes('money82');

        $this->assertCount(3, $authzUserAttributes);
        $this->assertEquals(['VIEWER', 'EDITOR'], $authzUserAttributes[self::ROLES_ATTRIBUTE]);

        $this->setupUserAttributeProviderWithUser('penny80');
        $this->mockResultsFor('penny80');
        $authzUserAttributes = $this->userAttributeProvider->getUserAttributes('penny80');

        $this->assertCount(3, $authzUserAttributes);
        $this->assertEquals(['VIEWER'], $authzUserAttributes[self::ROLES_ATTRIBUTE]);

        $this->setupUserAttributeProviderWithUser('sunny85');
        $this->mockResultsFor('sunny85');
        $authzUserAttributes = $this->userAttributeProvider->getUserAttributes('sunny85');

        $this->assertCount(3, $authzUserAttributes);
        $this->assertEquals([], $authzUserAttributes[self::ROLES_ATTRIBUTE]);
    }

    public function testCaching(): void
    {
        // without caching
        $this->setupUserAttributeProviderWithUser('money82');
        $this->mockResultsFor('money82');
        $authzUserAttributes = $this->userAttributeProvider->getUserAttributes('money82');

        $this->mockResultsFor('foo');
        $authzUserAttributesCached = $this->userAttributeProvider->getUserAttributes('money82');
        $this->assertNotSame($authzUserAttributes, $authzUserAttributesCached);

        // with caching
        $this->userAttributeProvider->setCache(new ArrayAdapter());
        $this->mockResultsFor('money82');
        $authzUserAttributes = $this->userAttributeProvider->getUserAttributes('money82');
        $this->assertCount(3, $authzUserAttributes);
        $this->assertEquals(['VIEWER', 'EDITOR'], $authzUserAttributes[self::ROLES_ATTRIBUTE]);

        $this->mockResultsFor('foo');
        // no new LDAP request since user is found in cache
        $authzUserAttributesCached = $this->userAttributeProvider->getUserAttributes('money82');

        $this->assertSame($authzUserAttributes, $authzUserAttributesCached);
    }

    public function testDefaultValueLdapAttributeNotFound()
    {
        $this->setupUserAttributeProviderWithUser('honey90');
        $this->mockResultsFor('honey90');
        $authzUserAttributes = $this->userAttributeProvider->getUserAttributes('honey90');

        $this->assertCount(3, $authzUserAttributes);
        $this->assertEquals(['DEFAULT'], $authzUserAttributes[self::ROLES_ATTRIBUTE]);
    }

    public function testDefaultValueLdapAttributeNotMapped()
    {
        $this->setupUserAttributeProviderWithUser('money82');
        $this->mockResultsFor('money82');
        $authzUserAttributes = $this->userAttributeProvider->getUserAttributes('money82');

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
        // expecting all default values
        $this->setupUserAttributeProviderWithUser();
        $this->assertEquals([
            self::ROLES_ATTRIBUTE => ['DEFAULT'],
            self::MISC_ATTRIBUTE => 0,
            self::MISC_ARRAY_ATTRIBUTE => [1, 2, 3],
        ],
            $this->userAttributeProvider->getUserAttributes(null));
    }

    public function testUnauthenticated()
    {
        // this is allowed for debugging purposes
        $this->setupUserAttributeProviderWithUser(isAuthenticated: false);
        $this->mockResultsFor('money82');
        $authzUserAttributes = $this->userAttributeProvider->getUserAttributes('money82');

        $this->assertCount(3, $authzUserAttributes);
        $this->assertEquals(['VIEWER', 'EDITOR'], $authzUserAttributes[self::ROLES_ATTRIBUTE]);
    }

    public function testUserIdentifierMismatch()
    {
        // it's allowed to request attributes for users other than the logged-in user
        $this->setupUserAttributeProviderWithUser('foo');
        $this->mockResultsFor('money82');
        $authzUserAttributes = $this->userAttributeProvider->getUserAttributes('money82');
        $this->assertCount(3, $authzUserAttributes);
        $this->assertEquals(['VIEWER', 'EDITOR'], $authzUserAttributes[self::ROLES_ATTRIBUTE]);
    }

    public function testUserNotFound()
    {
        $this->setupUserAttributeProviderWithUser('foo');
        $this->mockResultsFor();
        // expecting all default values
        $this->setupUserAttributeProviderWithUser();
        $this->assertEquals([
            self::ROLES_ATTRIBUTE => ['DEFAULT'],
            self::MISC_ATTRIBUTE => 0,
            self::MISC_ARRAY_ATTRIBUTE => [1, 2, 3],
        ],
            $this->userAttributeProvider->getUserAttributes('foo'));
    }

    public function testMultipleAttributeDeclarationsException()
    {
        $this->setupUserAttributeProviderWithUser();

        $config = $this->createConfig();
        // add duplicate attribute entry
        $config[Configuration::USER_ATTRIBUTE_PROVIDER_ATTRIBUTE][Configuration::ATTRIBUTES_ATTRIBUTE][] = [
            Configuration::NAME_ATTRIBUTE => self::ROLES_ATTRIBUTE,
            Configuration::IS_ARRAY_ATTRIBUTE => true,
            Configuration::LDAP_ATTRIBUTE_ATTRIBUTE => self::LDAP_ROLES_ATTRIBUTE_NAME,
        ];
        $this->expectException(\RuntimeException::class);
        $this->userAttributeProvider->setConfig($config[Configuration::USER_ATTRIBUTE_PROVIDER_ATTRIBUTE]);
    }

    /**
     * @param string|null $userIdentifier null means empty result set -> user not found
     */
    private function mockResultsFor(?string $userIdentifier = null): void
    {
        $results = match ($userIdentifier) {
            'money82' => [
                [
                    self::LDAP_IDENTIFIER_ATTRIBUTE_NAME => ['money82'],
                    self::LDAP_ROLES_ATTRIBUTE_NAME => ['VIEWER', 'EDITOR'],
                ],
            ],
            'penny80' => [
                [
                    self::LDAP_IDENTIFIER_ATTRIBUTE_NAME => ['penny80'],
                    self::LDAP_ROLES_ATTRIBUTE_NAME => ['VIEWER'],
                ],
            ],
            'sunny85' => [
                [
                    self::LDAP_IDENTIFIER_ATTRIBUTE_NAME => ['sunny85'],
                    self::LDAP_ROLES_ATTRIBUTE_NAME => [],
                ],
            ],
            'honey90' => [
                [
                    self::LDAP_IDENTIFIER_ATTRIBUTE_NAME => ['honey90'],
                ],
            ],
            default => [],
        };

        LdapConnectionProviderTest::mockResults($this->ldapConnectionProvider, $results);
    }
}
