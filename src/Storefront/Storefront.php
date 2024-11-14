<?php declare(strict_types=1);

namespace Shopware\Storefront;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\DependencyInjection\DisableTemplateCachePass;
use Shopware\Storefront\DependencyInjection\StorefrontMigrationReplacementCompilerPass;
use Shopware\Storefront\Framework\ThemeInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * @internal
 */
#[Package('storefront')]
class Storefront extends Bundle implements ThemeInterface
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $this->buildDefaultConfig($container);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection'));
        $loader->load('services.xml');
        $loader->load('seo.xml');
        $loader->load('controller.xml');
        $loader->load('theme.xml');
        if (!Feature::isActive('v6.7.0.0')) {
            $loader->load('theme_6_6_0.xml');
        }

        $container->setParameter('storefrontRoot', $this->getPath());

        $container->addCompilerPass(new DisableTemplateCachePass());
        $container->addCompilerPass(new StorefrontMigrationReplacementCompilerPass());
    }
}
