<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Ldap;

use Dbp\Relay\CoreBundle\HealthCheck\CheckInterface;
use Dbp\Relay\CoreBundle\HealthCheck\CheckOptions;
use Dbp\Relay\CoreBundle\HealthCheck\CheckResult;

class HealthCheck implements CheckInterface
{
    private LdapConnectionProvider $ldapConnectionProvider;

    public function __construct(LdapConnectionProvider $ldapConnectionProvider)
    {
        $this->ldapConnectionProvider = $ldapConnectionProvider;
    }

    public function getName(): string
    {
        return 'core-connector-ldap';
    }

    public function check(CheckOptions $options): array
    {
        $results = [];
        foreach ($this->ldapConnectionProvider->getConnectionIdentifiers() as $connectionIdentifier) {
            $ldapConnection = $this->ldapConnectionProvider->getConnection($connectionIdentifier);
            if ($ldapConnection instanceof LdapConnection) {
                $results[] = $this->checkMethod('Check if we can connect to the LDAP connection '.$connectionIdentifier, [$ldapConnection, 'checkConnection']);
                $results[] = $this->checkMethod('Check if all attributes are available for '.$connectionIdentifier, [$ldapConnection, 'checkAttributesExist']);
            }
        }

        return $results;
    }

    private function checkMethod(string $description, callable $func): CheckResult
    {
        $result = new CheckResult($description);
        try {
            $func();
        } catch (\Throwable $e) {
            $result->set(CheckResult::STATUS_FAILURE, $e->getMessage(), ['exception' => $e]);

            return $result;
        }
        $result->set(CheckResult::STATUS_SUCCESS);

        return $result;
    }
}
