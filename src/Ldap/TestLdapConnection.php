<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Ldap;

use Dbp\Relay\CoreBundle\Rest\Options;
use Dbp\Relay\CoreBundle\Rest\Query\Sorting\Sorting;
use Illuminate\Support\Collection;

/**
 * Test LDAP connection used for unit testing.
 */
class TestLdapConnection extends LdapConnection
{
    /** @var array[] */
    private array $testEntries;

    public function __construct(array $config = [], array $testEntries = [])
    {
        parent::__construct($config);

        $this->testEntries = $testEntries;
    }

    public function getTestEntries(): array
    {
        return $this->testEntries;
    }

    public function setTestEntries(array $testEntries): void
    {
        $this->testEntries = $testEntries;
    }

    protected function getEntriesInternal(int $currentPageNumber, int $maxNumItemsPerPage, array $options = []): array
    {
        // TODO: consider filters
        $sorting = Options::getSorting($options);
        if ($sorting && $sortField = ($sorting->getSortFields()[0] ?? null)) {
            $testEntryCollection = new Collection($this->testEntries);
            $allResults = $testEntryCollection->sortBy(Sorting::getPath($sortField), \SORT_REGULAR,
                Sorting::getDirection($sortField) === Sorting::DIRECTION_DESCENDING)->toArray();
        } else {
            $allResults = $this->testEntries;
        }

        $testUsers = [];
        foreach (array_slice($allResults, ($currentPageNumber - 1) * $maxNumItemsPerPage, $maxNumItemsPerPage) as $testUser) {
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

        foreach ($this->testEntries as $testUser) {
            $testUserAttributeValue = $testUser[$attributeName] ?? null;
            if ($testUserAttributeValue === null) {
                throw new LdapException(sprintf('user attribute \'%s\' not found', $attributeName));
            } elseif (is_array($testUserAttributeValue) ?
                $testUserAttributeValue[array_key_first($testUserAttributeValue)] === $attributeValue :
                $testUserAttributeValue === $attributeValue) {
                return new TestLdapEntry($testUser);
            }
        }

        throw new LdapException(sprintf("User with '%s' attribute value '%s' could not be found!", $attributeName, $attributeValue), LdapException::ENTRY_NOT_FOUND);
    }
}
