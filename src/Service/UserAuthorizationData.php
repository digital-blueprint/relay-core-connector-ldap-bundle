<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Service;

class UserAuthorizationData
{
    /** @var string[] */
    public $roles;
    /** @var mixed[] */
    public $attributes;
}
