<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Ldap;

class TestLdapEntry implements LdapEntryInterface
{
    private array $attributes;

    public function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    public function getAttributeValue(string $attributeName, mixed $defaultValue = null): mixed
    {
        return $this->attributes[$attributeName] ?? $defaultValue;
    }

    public function getFirstAttributeValue(string $attributeName, mixed $defaultValue = null): mixed
    {
        return $this->attributes[$attributeName][0] ?? $defaultValue;
    }

    public function getAttributeValues(): array
    {
        return $this->attributes;
    }
}
