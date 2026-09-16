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

    /**
     * Domains Shopware operates service registries on, covering the production registry as well as the ones
     * used by staging installations.
     */
    private const TRUSTED_REGISTRY_DOMAINS = ['shopware.io'];

    public function prepend(ContainerBuilder $container): void
    {
        $container->setParameter('shopware.service_registry.trusted_domains', $this->trustedDomains($container));
        $container->setParameter('shopware.service_registry.url', '%env(service-registry-url:SERVICE_REGISTRY_URL)%');

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
     * The registry decides which services a shop installs and where their code is downloaded from, so in
     * production `SERVICE_REGISTRY_URL` has to stay on a domain Shopware operates. Any other value is
     * ignored, which prevents a mistyped or manipulated environment from pointing a live shop at a foreign
     * registry. Other environments are unrestricted so they can use a local or mocked registry.
     *
     * @return list<string>
     */
    private function trustedDomains(ContainerBuilder $container): array
    {
        if ($container->getParameter('kernel.environment') !== 'prod') {
            return [];
        }

        return self::TRUSTED_REGISTRY_DOMAINS;
    }
}
