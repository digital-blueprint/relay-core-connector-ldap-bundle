<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Service;

use Dbp\Relay\CoreBundle\Authorization\AbstractAuthorizationService;
use Dbp\Relay\CoreConnectorLdapBundle\DependencyInjection\Configuration;

class AuthorizationService extends AbstractAuthorizationService
{
    private const CURRENT_LDAP_USER_IDENTIFIER_ATTRIBUTE = 'clui';

    private ?string $currentLdapUserIdentifier = null;
    private bool $wasCurrentLdapUserIdentifierRetrieved = false;

    public function setConfig(array $config): void
    {
        $authorizationAttributes = [];
        $authorizationAttributes[self::CURRENT_LDAP_USER_IDENTIFIER_ATTRIBUTE] =
            $config[Configuration::USER_ATTRIBUTE_CURRENT_LDAP_USER_IDENTIFIER_EXPRESSION_ATTRIBUTE];

        $this->setUpAccessControlPolicies(attributes: $authorizationAttributes);
    }

    public function getCurrentLdapUserIdentifier(): ?string
    {
        if (false === $this->wasCurrentLdapUserIdentifierRetrieved) {
            $this->currentLdapUserIdentifier =
                $this->getAttribute(self::CURRENT_LDAP_USER_IDENTIFIER_ATTRIBUTE);
            $this->wasCurrentLdapUserIdentifierRetrieved = true;
        }

        return $this->currentLdapUserIdentifier;
    }
}
