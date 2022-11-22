<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const NAME_ATTRIBUTE = 'name';
    public const ATTRIBUTES_ATTRIBUTE = 'attributes';
    public const LDAP_ATTRIBUTE_ATTRIBUTE = 'ldap_attribute';
    public const IS_ARRAY_ATTRIBUTE = 'is_array';
    public const DEFAULT_VALUE_ATTRIBUTE = 'default_value';
    public const DEFAULT_VALUES_ATTRIBUTE = 'default_values';
    public const LDAP_CONNECTION_ATTRIBUTE = 'ldap_connection';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dbp_relay_core_connector_ldap');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode(self::ATTRIBUTES_ATTRIBUTE)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode(self::NAME_ATTRIBUTE)
                                ->cannotBeEmpty()
                                ->info('The name of the authorization attribute')
                            ->end()
                            ->scalarNode(self::LDAP_ATTRIBUTE_ATTRIBUTE)
                                ->info('The source LDAP attribute that is mapped to the authorization attribute. If left blank, the attribute\'s value is not automatically mapped.')
                            ->end()
                             ->booleanNode(self::IS_ARRAY_ATTRIBUTE)
                                ->defaultFalse()
                            ->end()
                            ->scalarNode(self::DEFAULT_VALUE_ATTRIBUTE)
                                ->info('The default value for scalar (non-array) attributes. The default is null.')
                            ->end()
                            ->arrayNode(self::DEFAULT_VALUES_ATTRIBUTE)
                                ->info('The default value for array type attributes. The default is an empty array.')
                                ->scalarPrototype()->end()
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
