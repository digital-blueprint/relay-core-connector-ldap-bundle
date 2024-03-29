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

    public function getEntries(int $currentPageNumber, int $maxNumItemsPerPage, array $options = []): array
    {
        // TODO: consider filters
        $testUsers = [];
        foreach (array_slice($this->testUsers, ($currentPageNumber - 1) * $maxNumItemsPerPage, $maxNumItemsPerPage) as $testUser) {
            $testUsers[] = new TestLdapEntry($testUser);
        }

        return $testUsers;
    }

    /*
     * @throws LdapException
     */
    protected function getEntryByAttributeInternal(string $attributeName, string $attributeValue): LdapEntryInterface
    {
        if ($attributeName === '') {
            throw new LdapException('key user attribute must not be empty', LdapException::USER_ATTRIBUTE_UNDEFINED);
        }

        foreach ($this->testUsers as $testUser) {
            $testUserAttributeValue = $testUser[$attributeName] ?? null;
            if ($testUserAttributeValue === null) {
                throw new LdapException(sprintf('user attribute \'%s\' not found', $attributeName));
            } elseif (is_array($testUserAttributeValue) ?
                $testUserAttributeValue[array_key_first($testUserAttributeValue)] === $attributeValue :
                $testUserAttributeValue === $attributeValue) {
                return new TestLdapEntry($testUser);
            }
        }

        throw new LdapException(sprintf("User with '%s' attribute value '%s' could not be found!", $attributeName, $attributeValue), LdapException::USER_NOT_FOUND);
    }
}
