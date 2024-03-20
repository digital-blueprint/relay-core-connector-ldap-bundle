<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\DependencyInjection;

use Dbp\Relay\CoreBundle\Extension\ExtensionTrait;
use Dbp\Relay\CoreConnectorLdapBundle\Ldap\LdapConnectionProvider;
use Dbp\Relay\CoreConnectorLdapBundle\Service\UserAttributeProvider;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class DbpRelayCoreConnectorLdapExtension extends ConfigurableExtension
{
    use ExtensionTrait;

    public function loadInternal(array $mergedConfig, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.yaml');

        $definition = $container->getDefinition(UserAttributeProvider::class);
        $definition->addMethodCall('setConfig', [$mergedConfig[Configuration::USER_ATTRIBUTE_PROVIDER_ATTRIBUTE] ?? []]);

        $ldapCaches = [];
        foreach ($mergedConfig[Configuration::CONNECTIONS_ATTRIBUTE] ?? [] as $connection) {
            $connectionIdentifier = $connection[Configuration::LDAP_CONNECTION_IDENTIFIER_ATTRIBUTE];
            $cacheTtl = $connection[Configuration::LDAP_CACHE_TTL_ATTRIBUTE];
            if ($cacheTtl > 0) {
                $ldapCache = $container->setDefinition('dbp.relay.ldap.cache.ldap_'.$connectionIdentifier,
                    new ChildDefinition('cache.app'))
                    ->addTag('cache.pool', ['default_lifetime' => $cacheTtl]);
                $ldapCaches[$connectionIdentifier] = $ldapCache;
            }
        }

        $definition = $container->getDefinition(LdapConnectionProvider::class);
        $definition->addMethodCall('setConfig', [$mergedConfig]);
        $definition->addMethodCall('setCaches', [$ldapCaches]);
    }
}
