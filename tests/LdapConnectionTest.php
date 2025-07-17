<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Tests;

use Dbp\Relay\CoreBundle\Rest\Options;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\FilterException;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\FilterTreeBuilder;
use Dbp\Relay\CoreBundle\Rest\Query\Sort\Sort;
use Dbp\Relay\CoreConnectorLdapBundle\Ldap\LdapException;
use Dbp\Relay\CoreConnectorLdapBundle\TestUtils\TestLdapConnectionProvider;
use PHPUnit\Framework\TestCase;

class LdapConnectionTest extends TestCase
{
    private ?TestLdapConnectionProvider $testLdapConnectionProvider = null;

    protected function setUp(): void
    {
        $this->testLdapConnectionProvider = TestLdapConnectionProvider::create();
    }

    protected function tearDown(): void
    {
        $this->testLdapConnectionProvider->tearDown();
    }

    public function testGetEntriesEmpty(): void
    {
        $this->testLdapConnectionProvider->mockResults([]);
        $this->assertEmpty($this->testLdapConnectionProvider->getConnection(
            TestLdapConnectionProvider::DEFAULT_CONNECTION_IDENTIFIER)->getEntries());
    }

    public function testGetEntries(): void
    {
        $this->testLdapConnectionProvider->mockResults([
            [
                'cn' => ['john88'],
                'givenName' => ['John'],
                'sn' => ['Doe'],
            ],
            [
                'cn' => ['janed'],
                'givenName' => ['Jane'],
                'sn' => ['Doelle'],
            ],
            [
                'cn' => ['sm'],
                'givenName' => ['Mario'],
                'sn' => ['Super'],
            ],
        ]);
        $entries = $this->testLdapConnectionProvider->getConnection(TestLdapConnectionProvider::DEFAULT_CONNECTION_IDENTIFIER)->getEntries();
        $this->assertCount(3, $entries);

        $this->assertEquals('john88', $entries[0]->getFirstAttributeValue('cn'));
        $this->assertEquals('John', $entries[0]->getFirstAttributeValue('givenName'));
        $this->assertEquals('Doe', $entries[0]->getFirstAttributeValue('sn'));

        $this->assertEquals('janed', $entries[1]->getFirstAttributeValue('cn'));
        $this->assertEquals('Jane', $entries[1]->getFirstAttributeValue('givenName'));
        $this->assertEquals('Doelle', $entries[1]->getFirstAttributeValue('sn'));

        $this->assertEquals('sm', $entries[2]->getFirstAttributeValue('cn'));
        $this->assertEquals('Mario', $entries[2]->getFirstAttributeValue('givenName'));
        $this->assertEquals('Super', $entries[2]->getFirstAttributeValue('sn'));
    }

    public function testGetEntriesWithSort(): void
    {
        $this->testLdapConnectionProvider->mockResults([
            [
                'cn' => ['janed'],
                'givenName' => ['Jane'],
                'sn' => ['Doelle'],
            ],
            [
                'cn' => ['john88'],
                'givenName' => ['John'],
                'sn' => ['Doe'],
            ],
            [
                'cn' => ['sm'],
                'givenName' => ['Mario'],
                'sn' => ['Super'],
            ],
        ]);

        $options = [];
        Options::setSort($options, new Sort([Sort::createSortField('cn', Sort::DESCENDING_DIRECTION)]));
        $entries = $this->testLdapConnectionProvider->getConnection(TestLdapConnectionProvider::DEFAULT_CONNECTION_IDENTIFIER)->getEntries(options: $options);
        $this->assertCount(3, $entries);

        $this->assertEquals('sm', $entries[0]->getFirstAttributeValue('cn'));
        $this->assertEquals('Mario', $entries[0]->getFirstAttributeValue('givenName'));
        $this->assertEquals('Super', $entries[0]->getFirstAttributeValue('sn'));

        $this->assertEquals('john88', $entries[1]->getFirstAttributeValue('cn'));
        $this->assertEquals('John', $entries[1]->getFirstAttributeValue('givenName'));
        $this->assertEquals('Doe', $entries[1]->getFirstAttributeValue('sn'));

        $this->assertEquals('janed', $entries[2]->getFirstAttributeValue('cn'));
        $this->assertEquals('Jane', $entries[2]->getFirstAttributeValue('givenName'));
        $this->assertEquals('Doelle', $entries[2]->getFirstAttributeValue('sn'));
    }

    public function testGetEntriesWithSortTooManyResultsToSort(): void
    {
        $this->testLdapConnectionProvider->mockResults([
            [
                'cn' => ['janed'],
                'givenName' => ['Jane'],
                'sn' => ['Doelle'],
            ],
            [
                'cn' => ['john88'],
                'givenName' => ['John'],
                'sn' => ['Doe'],
            ],
            [
                'cn' => ['sm'],
                'givenName' => ['Mario'],
                'sn' => ['Super'],
            ],
            [
                'cn' => ['wl'],
                'givenName' => ['Wa'],
                'sn' => ['Luigi'],
            ],
        ]);

        try {
            $options = [];
            Options::setSort($options, new Sort([Sort::createSortField('cn', Sort::DESCENDING_DIRECTION)]));
            $this->testLdapConnectionProvider->getConnection(TestLdapConnectionProvider::DEFAULT_CONNECTION_IDENTIFIER)->getEntries(options: $options);
            $this->fail('LdapException not thrown as expected');
        } catch (LdapException $ldapException) {
            $this->assertEquals(LdapException::TOO_MANY_RESULTS_TO_SORT, $ldapException->getCode());
        }
    }

    /**
     * @throws FilterException
     */
    public function testGetEntriesWithFilter(): void
    {
        $this->testLdapConnectionProvider->mockResults([
            [
                'cn' => ['sm'],
                'givenName' => ['Mario'],
                'sn' => ['Super'],
            ],
        ], isQueryAsExpected: function (string $query) {
            return $query ===
                '(&('.TestLdapConnectionProvider::getObjectClassCriteria().')'.
                '(givenName=*'.TestLdapConnectionProvider::toExpectedValue('rio').'*))';
        });

        $options = [];
        Options::addFilter($options,
            FilterTreeBuilder::create()
                ->iContains('givenName', 'rio')
                ->createFilter());

        $entries = $this->testLdapConnectionProvider->getConnection(TestLdapConnectionProvider::DEFAULT_CONNECTION_IDENTIFIER)->getEntries(options: $options);
        $this->assertCount(1, $entries);
        $this->assertEquals('sm', $entries[0]->getFirstAttributeValue('cn'));
        $this->assertEquals('Mario', $entries[0]->getFirstAttributeValue('givenName'));
        $this->assertEquals('Super', $entries[0]->getFirstAttributeValue('sn'));
    }

    public function testGetEntriesWithInvalidFilter(): void
    {
        $options = [];
        Options::addFilter($options,
            FilterTreeBuilder::create()
                ->appendChild(new UndefinedConditionNode())
                ->createFilter());

        try {
            $this->testLdapConnectionProvider->expectConnection();
            $this->testLdapConnectionProvider->getConnection(
                TestLdapConnectionProvider::DEFAULT_CONNECTION_IDENTIFIER)->getEntries(options: $options);
            $this->fail('exception not thrown as expected');
        } catch (LdapException $ldapException) {
            $this->assertEquals(LdapException::FILTER_INVALID, $ldapException->getCode());
        }

        $options = [];
        Options::addFilter($options,
            FilterTreeBuilder::create()
                ->appendChild(new UndefinedLogicalNode())
                ->createFilter());

        try {
            $this->testLdapConnectionProvider->getConnection(TestLdapConnectionProvider::DEFAULT_CONNECTION_IDENTIFIER)->getEntries(options: $options);
            $this->fail('exception not thrown as expected');
        } catch (LdapException $ldapException) {
            $this->assertEquals(LdapException::FILTER_INVALID, $ldapException->getCode());
        }
    }

    public function testGetEntryNotFound(): void
    {
        $this->testLdapConnectionProvider->mockResults([], 'janed');
        try {
            $this->testLdapConnectionProvider->getConnection(TestLdapConnectionProvider::DEFAULT_CONNECTION_IDENTIFIER)->getEntryByAttribute('cn', 'janed');
            $this->fail('exception not thrown as expected');
        } catch (LdapException $ldapException) {
            $this->assertEquals(LdapException::ENTRY_NOT_FOUND, $ldapException->getCode());
        }
    }

    public function testGetEntry(): void
    {
        $this->testLdapConnectionProvider->mockResults([
            [
                'cn' => ['janed'],
                'givenName' => ['Jane'],
                'sn' => ['Doelle'],
            ],
        ], 'janed');
        $entry = $this->testLdapConnectionProvider->getConnection(TestLdapConnectionProvider::DEFAULT_CONNECTION_IDENTIFIER)->getEntryByAttribute('cn', 'janed');
        $this->assertEquals('janed', $entry->getFirstAttributeValue('cn'));
        $this->assertEquals('Jane', $entry->getFirstAttributeValue('givenName'));
        $this->assertEquals('Doelle', $entry->getFirstAttributeValue('sn'));
    }
}
