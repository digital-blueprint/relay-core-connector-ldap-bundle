<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Ldap;

interface LdapConnectionInterface
{
    /*
     * @throws LdapException
     */
    public function getEntryByAttribute(string $attributeName, string $attributeValue): LdapEntryInterface;

    /**
     * @return LdapEntryInterface[]
     *
     * @throws LdapException
     */
    public function getEntries(int $currentPageNumber = 1, int $maxNumItemsPerPage = 30, array $options = []): array;
}
