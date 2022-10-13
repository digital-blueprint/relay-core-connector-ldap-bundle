<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

class UserDataLoadedEvent extends Event
{
    public const NAME = 'dbp.relay.auth_connector_ldap_bundle.user_data_loaded';

    /** @var array */
    private $userData;

    /** @var array */
    private $userRoles;

    /** @var array */
    private $userAttributes;

    public function __construct(array $userData)
    {
        $this->userData = $userData;
        $this->userRoles = [];
        $this->userAttributes = [];
    }

    public function getUserData(): array
    {
        return $this->userData;
    }

    public function setUserAttributes(array $userAttributes)
    {
        $this->userAttributes = $userAttributes;
    }

    public function getUserAttributes(): array
    {
        return $this->userAttributes;
    }
}
