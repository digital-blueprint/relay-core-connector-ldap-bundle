<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Ldap;

use LdapRecord\Models\Model;

class LdapEntry implements LdapEntryInterface
{
    private Model $entry;

    public function __construct(Model $entry)
    {
        $this->entry = $entry;
    }

    public function getAttributeValue(string $attributeName, mixed $defaultValue = null): mixed
    {
        return $this->entry->getAttribute($attributeName, $defaultValue);
    }

    public function getFirstAttributeValue(string $attributeName, mixed $defaultValue = null): mixed
    {
        return $this->entry->getFirstAttribute($attributeName, $defaultValue);
    }

    public function getAttributeValues(): array
    {
        return $this->entry->getAttributes();
    }
}
