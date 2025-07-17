<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public const USER_ATTRIBUTE_NAME_ATTRIBUTE = 'name';

    public const USER_ATTRIBUTE_PROVIDER_ATTRIBUTE = 'user_attribute_provider';
    public const USER_ATTRIBUTE_ATTRIBUTES_ATTRIBUTE = 'attributes';
    public const USER_ATTRIBUTE_LDAP_CONNECTION_ATTRIBUTE = 'ldap_connection';
    public const USER_ATTRIBUTE_LDAP_USER_IDENTIFIER_ATTRIBUTE_ATTRIBUTE = 'ldap_user_identifier_attribute';
    public const USER_ATTRIBUTE_CURRENT_LDAP_USER_IDENTIFIER_EXPRESSION_ATTRIBUTE = 'current_ldap_user_identifier_expression';
    public const USER_ATTRIBUTE_LDAP_ATTRIBUTE_ATTRIBUTE = 'ldap_attribute';
    public const USER_ATTRIBUTE_IS_ARRAY_ATTRIBUTE = 'is_array';
    public const USER_ATTRIBUTE_DEFAULT_VALUE_ATTRIBUTE = 'default_value';
    public const USER_ATTRIBUTE_DEFAULT_VALUES_ATTRIBUTE = 'default_values';

    public const CONNECTIONS_ATTRIBUTE = 'connections';
    public const LDAP_CONNECTION_IDENTIFIER_ATTRIBUTE = 'identifier';
    public const LDAP_HOST_ATTRIBUTE = 'host';
    public const LDAP_BASE_DN_ATTRIBUTE = 'base_dn';
    public const LDAP_USERNAME_ATTRIBUTE = 'username';
    public const LDAP_PASSWORD_ATTRIBUTE = 'password';
    public const LDAP_ENCRYPTION_ATTRIBUTE = 'encryption';
    public const LDAP_OBJECT_CLASS_ATTRIBUTE = 'object_class';
    public const LDAP_CACHE_TTL_ATTRIBUTE = 'cache_ttl';
    public const LDAP_NUM_RESULT_ITEMS_WILL_SORT_LIMIT_ATTRIBUTE = 'result_items_will_sort_limit';

    /** @var int */
    public const LDAP_NUM_RESULT_ITEMS_WILL_SORT_LIMIT_DEFAULT = 10000;

    private const USER_ATTRIBUTE_CURRENT_LDAP_USER_IDENTIFIER_EXPRESSION_DEFAULT_VALUE = 'user.getIdentifier()';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('dbp_relay_core_connector_ldap');

        $treeBuilder->getRootNode()
            ->children()
               ->append(self::getUserAttributeProviderConfigNodeDefinition())
               ->append(self::getLdapConfigNodeDefinition())
            ->end()
        ;

        return $treeBuilder;
    }

    private static function getUserAttributeProviderConfigNodeDefinition(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder(self::USER_ATTRIBUTE_PROVIDER_ATTRIBUTE);

        return $treeBuilder->getRootNode()
            ->children()
                ->arrayNode(self::USER_ATTRIBUTE_ATTRIBUTES_ATTRIBUTE)
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode(self::USER_ATTRIBUTE_NAME_ATTRIBUTE)
                                ->cannotBeEmpty()
                                ->isRequired()
                                ->info('The name of the authorization attribute')
                            ->end()
                            ->scalarNode(self::USER_ATTRIBUTE_LDAP_ATTRIBUTE_ATTRIBUTE)
                                ->cannotBeEmpty()
                                ->isRequired()
                                ->info('The source LDAP attribute that is mapped to the authorization attribute. If left blank, the attribute\'s value is not automatically mapped.')
                            ->end()
                            ->booleanNode(self::USER_ATTRIBUTE_IS_ARRAY_ATTRIBUTE)
                                ->defaultFalse()
                            ->end()
                            ->scalarNode(self::USER_ATTRIBUTE_DEFAULT_VALUE_ATTRIBUTE)
                                ->info('The default value for scalar (non-array) attributes. The default is null.')
                            ->end()
                            ->arrayNode(self::USER_ATTRIBUTE_DEFAULT_VALUES_ATTRIBUTE)
                                ->info('The default value for array type attributes. The default is an empty array.')
                                ->scalarPrototype()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode(self::USER_ATTRIBUTE_LDAP_CONNECTION_ATTRIBUTE)
                   ->cannotBeEmpty()
                   ->isRequired()
                   ->info('The identifier of the LDAP connection to use. See the dbp_relay_ldap config for available connections.')
                ->end()
                ->scalarNode(self::USER_ATTRIBUTE_LDAP_USER_IDENTIFIER_ATTRIBUTE_ATTRIBUTE)
                   ->defaultValue('cn')
                   ->info('The LDAP attribute to use for the lookup of the current user identifier. The default is "cn".')
                ->end()
                ->scalarNode(self::USER_ATTRIBUTE_CURRENT_LDAP_USER_IDENTIFIER_EXPRESSION_ATTRIBUTE)
                    ->defaultValue(self::USER_ATTRIBUTE_CURRENT_LDAP_USER_IDENTIFIER_EXPRESSION_DEFAULT_VALUE)
                    ->info('The expression that is evaluated to get the current user identifier. The default is '.
                        self::USER_ATTRIBUTE_CURRENT_LDAP_USER_IDENTIFIER_EXPRESSION_DEFAULT_VALUE)
                ->end()
            ->end()
        ;
    }

    private static function getLdapConfigNodeDefinition(): NodeDefinition
    {
        $treeBuilder = new TreeBuilder(self::CONNECTIONS_ATTRIBUTE);

        return $treeBuilder->getRootNode()
                ->useAttributeAsKey(self::LDAP_CONNECTION_IDENTIFIER_ATTRIBUTE)
                ->arrayPrototype()
                    ->children()
                        ->scalarNode(self::LDAP_HOST_ATTRIBUTE)
                          ->cannotBeEmpty()
                          ->isRequired()
                        ->end()
                        ->scalarNode(self::LDAP_BASE_DN_ATTRIBUTE)
                        ->end()
                        ->scalarNode(self::LDAP_USERNAME_ATTRIBUTE)
                        ->end()
                        ->scalarNode(self::LDAP_PASSWORD_ATTRIBUTE)
                        ->end()
                        ->enumNode(self::LDAP_ENCRYPTION_ATTRIBUTE)
                            ->info('simple_tls uses port 636 and is sometimes referred to as "SSL", start_tls uses port 389 and is sometimes referred to as "TLS", plain means none')
                            ->values(['start_tls', 'simple_tls', 'plain'])
                            ->defaultValue('start_tls')
                        ->end()
                        ->scalarNode(self::LDAP_OBJECT_CLASS_ATTRIBUTE)
                        ->defaultValue('person')
                        ->end()
                        ->integerNode(self::LDAP_CACHE_TTL_ATTRIBUTE)
                          ->info('cache ttl. 0 indicates no caching.')
                          ->defaultValue(0)
                        ->end()
                        ->integerNode(self::LDAP_NUM_RESULT_ITEMS_WILL_SORT_LIMIT_ATTRIBUTE)
                            ->info('The maximum number of items that will be sorted without throwing an error.'.
                                'This is to prevent uncontrolled out of memory errors when sorting a large number of results sets. (default: '.
                                self::LDAP_NUM_RESULT_ITEMS_WILL_SORT_LIMIT_DEFAULT.')')
                            ->defaultValue(self::LDAP_NUM_RESULT_ITEMS_WILL_SORT_LIMIT_DEFAULT)
                        ->end()
                    ->end()
                ->end()
        ;
    }
}
