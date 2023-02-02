<?php declare(strict_types=1);

namespace Shopware\Core\Profiling;

use Composer\InstalledVersions;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Kernel;
use Shopware\Core\Profiling\Compiler\RemoveDevServices;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * @internal
 */
#[Package('core')]
class Profiling extends Bundle
{
    public function getTemplatePriority(): int
    {
        return -2;
    }

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

        if ($environment === 'dev') {
            $loader->load('services_dev.xml');
        }

        $container->addCompilerPass(new RemoveDevServices());
    }

    public function boot(): void
    {
        parent::boot();
        // The profiler registers all profiler integrations in the constructor
        // Therefor we need to get the service once to initialize it
        $this->container->get(Profiler::class);
    }

    public function configureRoutes(RoutingConfigurator $routes, string $environment): void
    {
        if (!InstalledVersions::isInstalled('symfony/web-profiler-bundle')) {
            return;
        }

        parent::configureRoutes($routes, $environment);
    }

    private function buildConfig(ContainerBuilder $container, string $environment): void
    {
        if (!InstalledVersions::isInstalled('symfony/web-profiler-bundle')) {
            return;
        }

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
