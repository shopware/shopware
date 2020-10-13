<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Framework\Adapter\Asset\AssetPackageService;
use Shopware\Core\Framework\Adapter\Filesystem\PrefixFilesystem;
use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Kernel;
use Symfony\Component\Cache\DependencyInjection\CacheCollectorPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\Bundle as SymfonyBundle;
use Symfony\Component\Routing\RouteCollectionBuilder;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

abstract class Bundle extends SymfonyBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $this->registerContainerFile($container);
        $this->registerMigrationPath($container);
        $this->registerFilesystem($container, 'private');
        $this->registerFilesystem($container, 'public');
        $this->registerEvents($container);

        // Can be removed when 4.4.14 is released and required in composer.json
        $removingPasses = $container->getCompilerPassConfig()->getBeforeRemovingPasses();
        $newPasses = [];

        foreach ($removingPasses as $removingPass) {
            if ($removingPass instanceof CacheCollectorPass) {
                $newPasses[] = new Adapter\Cache\CacheCollectorPass();
            } else {
                $newPasses[] = $removingPass;
            }
        }

        $container->getCompilerPassConfig()->setBeforeRemovingPasses($newPasses);
    }

    public function boot(): void
    {
        $this->container->get(AssetPackageService::class)->addAssetPackage($this->getName(), $this->getPath());

        parent::boot();
    }

    public function getMigrationNamespace(): string
    {
        return $this->getNamespace() . '\Migration';
    }

    public function getMigrationPath(): string
    {
        $migrationSuffix = str_replace(
            $this->getNamespace(),
            '',
            $this->getMigrationNamespace()
        );

        return $this->getPath() . str_replace('\\', '/', $migrationSuffix);
    }

    final public function getContainerPrefix(): string
    {
        return (new CamelCaseToSnakeCaseNameConverter())->normalize($this->getName());
    }

    public function configureRoutes(RouteCollectionBuilder $routes, string $environment): void
    {
        $fileSystem = new Filesystem();
        $confDir = $this->getPath() . '/Resources/config';

        if ($fileSystem->exists($confDir)) {
            $routes->import($confDir . '/{routes}/*' . Kernel::CONFIG_EXTS, '/', 'glob');
            $routes->import($confDir . '/{routes}/' . $environment . '/**/*' . Kernel::CONFIG_EXTS, '/', 'glob');
            $routes->import($confDir . '/{routes}' . Kernel::CONFIG_EXTS, '/', 'glob');
            $routes->import($confDir . '/{routes}_' . $environment . Kernel::CONFIG_EXTS, '/', 'glob');
        }
    }

    public function configureRouteOverwrites(RouteCollectionBuilder $routes, string $environment): void
    {
        $fileSystem = new Filesystem();
        $confDir = $this->getPath() . '/Resources/config';

        if ($fileSystem->exists($confDir)) {
            $routes->import($confDir . '/{routes_overwrite}' . Kernel::CONFIG_EXTS, '/', 'glob');
        }
    }

    /**
     * @feature-deprecated (flag:FEATURE_NEXT_9351) tag:v6.4.0 - Implement `getActionEventClasses` instead
     */
    protected function getActionEvents(): array
    {
        return [];
    }

    /**
     * Returns a list of all action event class references of this bundle. The events will be registered inside the `\Shopware\Core\Framework\Event\BusinessEventRegistry`.
     *
     * @return string[]
     */
    protected function getActionEventClasses(): array
    {
        return [];
    }

    protected function registerMigrationPath(ContainerBuilder $container): void
    {
        $migrationPath = $this->getMigrationPath();

        if (!is_dir($migrationPath)) {
            return;
        }

        $container->register(MigrationSource::class . '_' . $this->getName(), MigrationSource::class)
            ->addArgument($this->getName())
            ->addArgument([$migrationPath => $this->getMigrationNamespace()])
            ->addTag('shopware.migration_source');
    }

    protected function addCoreMigrationPath(ContainerBuilder $container, string $path, string $namespace): void
    {
        $container->getDefinition(MigrationSource::class . '.core')
            ->addMethodCall('addDirectory', [$path, $namespace]);
    }

    private function registerFilesystem(ContainerBuilder $container, string $key): void
    {
        $containerPrefix = $this->getContainerPrefix();
        $parameterKey = sprintf('shopware.filesystem.%s', $key);
        $serviceId = sprintf('%s.filesystem.%s', $containerPrefix, $key);

        $filesystem = new Definition(
            PrefixFilesystem::class,
            [
                new Reference($parameterKey),
                'plugins/' . $containerPrefix,
            ]
        );

        $container->setDefinition($serviceId, $filesystem);
    }

    private function registerEvents(ContainerBuilder $container): void
    {
        $definition = $container->getDefinition(BusinessEventRegistry::class);
        $definition->addMethodCall('addMultiple', [$this->getActionEvents()]);
        $definition->addMethodCall('addClasses', [$this->getActionEventClasses()]);
    }

    /**
     * Looks for service definition files inside the `Resources/config`
     * directory and loads either xml or yml files.
     */
    private function registerContainerFile(ContainerBuilder $container): void
    {
        $fileLocator = new FileLocator($this->getPath());
        $loaderResolver = new LoaderResolver([
            new XmlFileLoader($container, $fileLocator),
            new YamlFileLoader($container, $fileLocator),
            new PhpFileLoader($container, $fileLocator),
        ]);
        $delegatingLoader = new DelegatingLoader($loaderResolver);

        foreach (glob($this->getPath() . '/Resources/config/services.*') as $path) {
            $delegatingLoader->load($path);
        }
    }
}
