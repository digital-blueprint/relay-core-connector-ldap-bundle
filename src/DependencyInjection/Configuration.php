<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const NAME_ATTRIBUTE = 'name';
    public const ROLES_ATTRIBUTE = 'roles';
    public const ATTRIBUTES_ATTRIBUTE = 'attributes';
    public const LDAP_CONNECTION_ATTRIBUTE = 'ldap_connection';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dbp_relay_auth_connector_ldap');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode(self::ROLES_ATTRIBUTE)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode(self::NAME_ATTRIBUTE)
                            ->info('The name of the role')
                            ->example('ROLE_VIEWER')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode(self::ATTRIBUTES_ATTRIBUTE)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode(self::NAME_ATTRIBUTE)
                                ->info('The name of the attribute')
                                ->example('ORGANIZATION_UNITS')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode(self::LDAP_CONNECTION_ATTRIBUTE)
                    ->info('The identifier of the LDAP connection to use. See the dbp_relay_ldap config for available connections.')
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
