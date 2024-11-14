<?php declare(strict_types=1);

namespace Shopware\Core\Profiling;

use Composer\InstalledVersions;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Compiler\RemoveDevServices;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
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

        if (InstalledVersions::isInstalled('symfony/web-profiler-bundle')) {
            $this->buildDefaultConfig($container);
        }

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
        \assert($this->container instanceof ContainerInterface, 'Container is not set yet, please call setContainer() before calling boot(), see `src/Core/Kernel.php:186`.');

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
}
