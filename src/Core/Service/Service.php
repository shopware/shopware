<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\DependencyInjection\ServiceExtension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class Service extends Bundle
{
    public function getContainerExtension(): Extension
    {
        return new ServiceExtension();
    }

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection'));
        $loader->load('services.xml');
        parent::build($container);
    }
}
