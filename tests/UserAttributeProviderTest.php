<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Tests;

use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
use Dbp\Relay\CoreBundle\TestUtils\TestUserSession;
use Dbp\Relay\CoreBundle\User\UserAttributeException;
use Dbp\Relay\CoreConnectorLdapBundle\DependencyInjection\Configuration;
use Dbp\Relay\CoreConnectorLdapBundle\Service\AuthorizationService;
use Dbp\Relay\CoreConnectorLdapBundle\Service\UserAttributeProvider;
use Dbp\Relay\CoreConnectorLdapBundle\TestUtils\TestLdapConnectionProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class UserAttributeProviderTest extends TestCase
{
    public const ROLES_ATTRIBUTE = 'roles';
    public const MISC_ATTRIBUTE = 'misc';
    public const MISC_ARRAY_ATTRIBUTE = 'misc_array';

    public const LDAP_IDENTIFIER_ATTRIBUTE_NAME = 'cn';
    public const LDAP_ROLES_ATTRIBUTE_NAME = 'LDAP-ROLES';

    private ?UserAttributeProvider $userAttributeProvider = null;
    private ?TestLdapConnectionProvider $testLdapConnectionProvider = null;

    protected function setUp(): void
    {
        $this->testLdapConnectionProvider = TestLdapConnectionProvider::create();
    }

    protected function tearDown(): void
    {
        $this->testLdapConnectionProvider->tearDown();
    }

    private function setupUserAttributeProviderWithUser(?string $userIdentifier = 'testuser'): void
    {
        $config = $this->createConfig();
        $authorizationService = new AuthorizationService();
        TestAuthorizationService::setUp($authorizationService, $userIdentifier);
        $authorizationService->setConfig($config[Configuration::USER_ATTRIBUTE_PROVIDER_ATTRIBUTE]);

        $this->userAttributeProvider = new UserAttributeProvider(
            $this->testLdapConnectionProvider,
            new TestUserSession($userIdentifier, isAuthenticated: true),
            $authorizationService);
        $this->userAttributeProvider->setConfig($config[Configuration::USER_ATTRIBUTE_PROVIDER_ATTRIBUTE]);
    }

    private function createConfig(): array
    {
        return [
            Configuration::USER_ATTRIBUTE_PROVIDER_ATTRIBUTE => [
                Configuration::USER_ATTRIBUTE_LDAP_CONNECTION_ATTRIBUTE => TestLdapConnectionProvider::DEFAULT_CONNECTION_IDENTIFIER,
                Configuration::USER_ATTRIBUTE_LDAP_USER_IDENTIFIER_ATTRIBUTE_ATTRIBUTE => self::LDAP_IDENTIFIER_ATTRIBUTE_NAME,
                Configuration::USER_ATTRIBUTE_CURRENT_LDAP_USER_IDENTIFIER_EXPRESSION_ATTRIBUTE => 'user.getIdentifier()',
                Configuration::USER_ATTRIBUTE_ATTRIBUTES_ATTRIBUTE => [
                    [
                        Configuration::USER_ATTRIBUTE_NAME_ATTRIBUTE => self::ROLES_ATTRIBUTE,
                        Configuration::USER_ATTRIBUTE_IS_ARRAY_ATTRIBUTE => true,
                        Configuration::USER_ATTRIBUTE_LDAP_ATTRIBUTE_ATTRIBUTE => self::LDAP_ROLES_ATTRIBUTE_NAME,
                        Configuration::USER_ATTRIBUTE_DEFAULT_VALUES_ATTRIBUTE => ['DEFAULT'],
                    ],
                    [
                        Configuration::USER_ATTRIBUTE_NAME_ATTRIBUTE => self::MISC_ATTRIBUTE,
                        Configuration::USER_ATTRIBUTE_IS_ARRAY_ATTRIBUTE => false,
                        Configuration::USER_ATTRIBUTE_DEFAULT_VALUE_ATTRIBUTE => 0,
                    ],
                    [
                        Configuration::USER_ATTRIBUTE_NAME_ATTRIBUTE => self::MISC_ARRAY_ATTRIBUTE,
                        Configuration::USER_ATTRIBUTE_IS_ARRAY_ATTRIBUTE => true,
                        Configuration::USER_ATTRIBUTE_DEFAULT_VALUES_ATTRIBUTE => [1, 2, 3],
                    ],
                ],
            ],
        ];
    }

    /**
     * @throws UserAttributeException
     */
    public function testAttributeMapping()
    {
        $this->setupUserAttributeProviderWithUser('money82');
        $this->mockResultsFor('money82');
        $this->assertEquals(['VIEWER', 'EDITOR'],
            $this->userAttributeProvider->getUserAttribute('money82', self::ROLES_ATTRIBUTE));

        $this->setupUserAttributeProviderWithUser('penny80');
        $this->mockResultsFor('penny80');
        $this->assertEquals(['VIEWER'], $this->userAttributeProvider->getUserAttribute('penny80', self::ROLES_ATTRIBUTE));

        $this->setupUserAttributeProviderWithUser('sunny85');
        $this->mockResultsFor('sunny85');
        $this->assertEquals([], $this->userAttributeProvider->getUserAttribute('sunny85', self::ROLES_ATTRIBUTE));
    }

    /**
     * @throws UserAttributeException
     */
    public function testCaching(): void
    {
        // without caching
        $this->setupUserAttributeProviderWithUser('money82');
        $this->mockResultsFor('money82');
        $authzUserAttributes = $this->userAttributeProvider->getUserAttribute('money82', self::ROLES_ATTRIBUTE);

        $this->mockResultsFor('money82');
        $authzUserAttributeCached = $this->userAttributeProvider->getUserAttribute('money82', self::ROLES_ATTRIBUTE);
        $this->assertSame($authzUserAttributes, $authzUserAttributeCached);

        // with caching
        $this->userAttributeProvider->setCache(new ArrayAdapter());

        $this->mockResultsFor('money82');
        $authzUserAttributes = $this->userAttributeProvider->getUserAttribute('money82', self::ROLES_ATTRIBUTE);
        // no new LDAP request since user is found in cache
        $authzUserAttributeCached = $this->userAttributeProvider->getUserAttribute('money82', self::ROLES_ATTRIBUTE);
        $this->assertSame($authzUserAttributes, $authzUserAttributeCached);
    }

    /**
     * @throws UserAttributeException
     */
    public function testDefaultValueLdapAttributeNotFound()
    {
        $this->setupUserAttributeProviderWithUser('honey90');
        $this->mockResultsFor('honey90');
        $this->assertEquals(['DEFAULT'], $this->userAttributeProvider->getUserAttribute('honey90', self::ROLES_ATTRIBUTE));
    }

    /**
     * @throws UserAttributeException
     */
    public function testDefaultValueLdapAttributeNotMapped()
    {
        $this->setupUserAttributeProviderWithUser('money82');
        $this->mockResultsFor('money82');
        $this->userAttributeProvider->setCache(new ArrayAdapter());
        $this->assertEquals(0, $this->userAttributeProvider->getUserAttribute('money82', self::MISC_ATTRIBUTE));
        $this->assertEquals([1, 2, 3], $this->userAttributeProvider->getUserAttribute('money82', self::MISC_ARRAY_ATTRIBUTE));
    }

    /**
     * @throws UserAttributeException
     */
    public function testWithoutUserId()
    {
        // expecting all default values
        $this->setupUserAttributeProviderWithUser(null);

        $this->assertEquals(['DEFAULT'], $this->userAttributeProvider->getUserAttribute(null, self::ROLES_ATTRIBUTE));
        $this->assertEquals(0, $this->userAttributeProvider->getUserAttribute(null, self::MISC_ATTRIBUTE));
        $this->assertEquals([1, 2, 3], $this->userAttributeProvider->getUserAttribute(null, self::MISC_ARRAY_ATTRIBUTE));
    }

    public function testUserNotFound()
    {
        $this->setupUserAttributeProviderWithUser('foo');
        $this->mockResultsFor();
        // expecting all default values
        $this->assertEquals(['DEFAULT'], $this->userAttributeProvider->getUserAttribute('foo', self::ROLES_ATTRIBUTE));

        $this->mockResultsFor();
        $this->assertEquals(0, $this->userAttributeProvider->getUserAttribute('foo', self::MISC_ATTRIBUTE));

        $this->mockResultsFor();
        $this->assertEquals([1, 2, 3], $this->userAttributeProvider->getUserAttribute('foo', self::MISC_ARRAY_ATTRIBUTE));
    }

    public function testMultipleAttributeDeclarationsException()
    {
        $this->setupUserAttributeProviderWithUser();

        $config = $this->createConfig();
        // add duplicate attribute entry
        $config[Configuration::USER_ATTRIBUTE_PROVIDER_ATTRIBUTE][Configuration::USER_ATTRIBUTE_ATTRIBUTES_ATTRIBUTE][] = [
            Configuration::USER_ATTRIBUTE_NAME_ATTRIBUTE => self::ROLES_ATTRIBUTE,
            Configuration::USER_ATTRIBUTE_IS_ARRAY_ATTRIBUTE => true,
            Configuration::USER_ATTRIBUTE_LDAP_ATTRIBUTE_ATTRIBUTE => self::LDAP_ROLES_ATTRIBUTE_NAME,
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

        $this->testLdapConnectionProvider->mockResults($results, $userIdentifier);
    }
}
