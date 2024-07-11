<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Ldap;

class LdapException extends \RuntimeException
{
    public const SERVER_CONNECTION_FAILED = 1;
    public const ENTRY_NOT_FOUND = 2;
    public const USER_ATTRIBUTE_UNDEFINED = 3;
    public const LDAP_CONNECTION_UNDEFINED = 4;
    public const FILTER_INVALID = 5;
}
