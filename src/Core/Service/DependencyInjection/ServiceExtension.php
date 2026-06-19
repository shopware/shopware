<?php declare(strict_types=1);

namespace Shopware\Core\Service\DependencyInjection;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ServiceExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('framework', [
            'http_client' => [
                'scoped_clients' => [
                    'service_registry.http_client' => [
                        'base_uri' => '%env(SERVICE_REGISTRY_URL)%',
                        'max_duration' => 5,
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param array<array<mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
    }
}
