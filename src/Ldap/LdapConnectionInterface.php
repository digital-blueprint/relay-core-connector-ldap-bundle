<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Ldap;

interface LdapConnectionInterface
{
    /*
     * @throws LdapException
     */
    public function getEntryByAttribute(string $attributeName, string $attributeValue): LdapEntryInterface;

    /*
     * @throws LdapException
     */
    public function getEntryByIdentifier(string $identifier): LdapEntryInterface;

    /**
     * @return LdapEntryInterface[]
     *
     * @throws LdapException
     */
    public function getEntries(int $currentPageNumber, int $maxNumItemsPerPage, array $options = []): array;
}
