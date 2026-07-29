<?php declare(strict_types=1);

namespace Shopware\Core\Service\DependencyInjection;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

#[Package('framework')]
class ServiceExtension extends Extension implements PrependExtensionInterface
{
    public const DEFAULT_REGISTRY_URL = 'https://registry.services.shopware.io';

    public function prepend(ContainerBuilder $container): void
    {
        $container->setParameter('shopware.service_registry.url', $this->resolveRegistryUrl($container));

        $container->prependExtensionConfig('framework', [
            'http_client' => [
                'scoped_clients' => [
                    'service_registry.http_client' => [
                        'base_uri' => '%shopware.service_registry.url%',
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

    /**
     * The registry decides which services a shop installs and where their code is downloaded from, so
     * `SERVICE_REGISTRY_URL` is only read outside of production. In production the URL is fixed, which
     * prevents a leaked or manipulated environment from pointing a live shop at a different registry.
     */
    private function resolveRegistryUrl(ContainerBuilder $container): string
    {
        if ($container->getParameter('kernel.environment') === 'prod') {
            return self::DEFAULT_REGISTRY_URL;
        }

        return '%env(SERVICE_REGISTRY_URL)%';
    }
}
