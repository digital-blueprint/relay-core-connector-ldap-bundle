<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Ldap;

/**
 * Test LDAP connection used for unit testing.
 */
class TestLdapConnection extends LdapConnection
{
    /** @var array[] */
    private array $testUsers;

    public function __construct(array $config, array $testUsers = [])
    {
        parent::__construct($config);

        $this->testUsers = $testUsers;
    }

    /**
     * @return array[]
     */
    public function getUsers(int $currentPageNumber, int $maxNumItemsPerPage, array $options = []): array
    {
        // TODO: consider filters
        return $this->testUsers;
    }

    /*
     * @throws LdapException
     */
    protected function getUserAttributesByAttributeInternal(string $userAttributeName, string $userAttributeValue): array
    {
        if ($userAttributeName === '') {
            throw new LdapException('key user attribute must not be empty', LdapException::USER_ATTRIBUTE_UNDEFINED);
        }

        foreach ($this->testUsers as $testUser) {
            $testUserAttributeValue = $testUser[$userAttributeName] ?? null;
            if ($testUserAttributeValue === null) {
                throw new LdapException(sprintf('user attribute \'%s\' not found', $userAttributeName));
            } elseif (is_array($testUserAttributeValue) ?
                $testUserAttributeValue[array_key_first($testUserAttributeValue)] === $userAttributeValue :
                $testUserAttributeValue === $userAttributeValue) {
                return $testUser;
            }
        }

        throw new LdapException(sprintf("User with '%s' attribute value '%s' could not be found!", $userAttributeName, $userAttributeValue), LdapException::USER_NOT_FOUND);
    }
}
