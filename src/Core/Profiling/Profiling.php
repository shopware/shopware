<?php declare(strict_types=1);

namespace Shopware\Core\Profiling;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Kernel;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

/**
 * @internal
 */
class Profiling extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        /** @var string $environment */
        $environment = $container->getParameter('kernel.environment');

        parent::build($container);

        $this->buildConfig($container, $environment);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('services.xml');
    }

    public function boot(): void
    {
        parent::boot();
        // The profiler registers all profiler integrations in the constructor
        // Therefor we need to get the service once to initialize it
        $this->container->get(Profiler::class);
    }

    private function buildConfig(ContainerBuilder $container, string $environment): void
    {
        $locator = new FileLocator('Resources/config');

        $resolver = new LoaderResolver([
            new YamlFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
        ]);

        $configLoader = new DelegatingLoader($resolver);

        $confDir = $this->getPath() . '/Resources/config';

        $configLoader->load($confDir . '/{packages}/*' . Kernel::CONFIG_EXTS, 'glob');
        $configLoader->load($confDir . '/{packages}/' . $environment . '/*' . Kernel::CONFIG_EXTS, 'glob');
    }
}
