<?php declare(strict_types=1);

namespace Shopware\Core\Service\DependencyInjection;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * @internal
 */
#[Package('core')]
class ServiceExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();

        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('shopware.services.registry_url', $config['registry_url']);
        $container->setParameter('shopware.services.enabled', $config['enabled']);
    }
}
