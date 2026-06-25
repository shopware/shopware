<?php declare(strict_types=1);

namespace Shopware\Administration;

use Pentatrion\ViteBundle\PentatrionViteBundle;
use Shopware\Administration\DependencyInjection\AdministrationMigrationCompilerPass;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * @internal
 */
#[Package('framework')]
class Administration extends Bundle
{
    public function getTemplatePriority(): int
    {
        return -1;
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $this->buildDefaultConfig($container);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('services.xml');
        $loader->load('framework.xml');

        $container->addCompilerPass(new AdministrationMigrationCompilerPass());
    }

    public function getAdditionalBundles(AdditionalBundleParameters $parameters): array
    {
        return [
            new PentatrionViteBundle(),
        ];
    }
}
