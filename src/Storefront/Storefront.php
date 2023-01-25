<?php declare(strict_types=1);

namespace Shopware\Storefront;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Kernel;
use Shopware\Storefront\DependencyInjection\DisableTemplateCachePass;
use Shopware\Storefront\DependencyInjection\ReverseProxyCompilerPass;
use Shopware\Storefront\DependencyInjection\StorefrontMigrationReplacementCompilerPass;
use Shopware\Storefront\Framework\ThemeInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

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

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection'));
        $loader->load('services.xml');
        $loader->load('seo.xml');
        $loader->load('controller.xml');
        $loader->load('theme.xml');

        /** @var string $environment */
        $environment = $container->getParameter('kernel.environment');

        $this->buildConfig($container, $environment);

        $container->setParameter('storefrontRoot', $this->getPath());

        $container->addCompilerPass(new DisableTemplateCachePass());
        $container->addCompilerPass(new ReverseProxyCompilerPass());
        $container->addCompilerPass(new StorefrontMigrationReplacementCompilerPass());
    }

    private function buildConfig(ContainerBuilder $container, string $environment): void
    {
        $locator = new FileLocator('Resources/config');

        $resolver = new LoaderResolver([
            new XmlFileLoader($container, $locator),
            new YamlFileLoader($container, $locator),
            new IniFileLoader($container, $locator),
            new PhpFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
            new DirectoryLoader($container, $locator),
            new ClosureLoader($container),
        ]);

        $configLoader = new DelegatingLoader($resolver);

        $confDir = $this->getPath() . '/Resources/config';

        $configLoader->load($confDir . '/{packages}/*' . Kernel::CONFIG_EXTS, 'glob');
        $configLoader->load($confDir . '/{packages}/' . $environment . '/*' . Kernel::CONFIG_EXTS, 'glob');
    }
}
