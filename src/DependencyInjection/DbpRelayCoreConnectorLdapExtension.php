<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\DependencyInjection;

use Dbp\Relay\CoreBundle\Extension\ExtensionTrait;
use Dbp\Relay\CoreConnectorLdapBundle\Service\AuthorizationDataProvider;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

class DbpRelayCoreConnectorLdapExtension extends ConfigurableExtension
{
    use ExtensionTrait;

    public function loadInternal(array $mergedConfig, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.yaml');

        $userCache = $container->register('dbp_api.cache.ldap_authorization_data_provider.user', FilesystemAdapter::class);
        $userCache->setArguments(['core-user', 60, '%kernel.cache_dir%/dbp/ldap-authorization-data-provider-user']);
        $userCache->addTag('cache.pool');

        $definition = $container->getDefinition(AuthorizationDataProvider::class);
        $definition->addMethodCall('setConfig', [$mergedConfig]);
        $definition->addMethodCall('setCache', [$userCache]);
    }
}
