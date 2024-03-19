<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Ldap;

interface LdapConnectionInterface
{
    /*
     * @throws LdapException
     */
    public function getUserAttributesByAttribute(string $userAttributeName, string $userAttributeValue): array;

    /*
     * @throws LdapException
     */
    public function getUserAttributesByIdentifier(string $identifier): array;

    /**
     * @return array[]
     *
     * @throws LdapException
     */
    public function getUsers(int $currentPageNumber, int $maxNumItemsPerPage, array $options = []): array;
}
