<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Ldap;

interface LdapEntryInterface
{
    public function getAttributeValue(string $attributeName, mixed $defaultValue = null): mixed;

    public function getFirstAttributeValue(string $attributeName, mixed $defaultValue = null): mixed;

    public function getAttributeValues(): array;
}
