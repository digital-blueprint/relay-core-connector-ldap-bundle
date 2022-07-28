<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Tests;

use Dbp\Relay\CoreConnectorLdapBundle\Service\LdapService;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;
use PHPUnit\Framework\TestCase;

class LdapServiceTest extends TestCase
{
    public function testFoo()
    {
        $connection = new Connection();
        Container::addConnection($connection);
        $fake = DirectoryFake::setup()->actingAs('user');
        $ldap = $fake->getLdapConnection();
        assert($ldap instanceof LdapFake);
        $ldap->expect(['bind' => true]);

        $service = new LdapService();
        $service->setLdapConfig(['encryption' => 'simple_tls', 'username' => 'user', 'password' => 'pswd']);
        $service->setLdapConnection($ldap);
        $this->assertTrue(true);
    }
}
