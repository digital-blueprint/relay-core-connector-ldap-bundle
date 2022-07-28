<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dbp_relay_core_connector_ldap');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $ldapBuilder = new TreeBuilder('ldap');
        $ldapNode = $ldapBuilder->getRootNode()
            ->children()
                ->scalarNode('host')->end()
                ->scalarNode('base_dn')->end()
                ->scalarNode('username')->end()
                ->scalarNode('password')->end()
                ->enumNode('encryption')
                    ->info('simple_tls uses port 636 and is sometimes referred to as "SSL", start_tls uses port 389 and is sometimes referred to as "TLS"')
                    ->values(['start_tls', 'simple_tls'])
                    ->defaultValue('start_tls')
                ->end()
                ->scalarNode('id_attribute')->end()
            ->end();

        $rootNode->append($ldapNode);

        return $treeBuilder;
    }
}
