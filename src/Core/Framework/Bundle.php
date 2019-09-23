<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Framework\Asset\AssetPackageService;
use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Shopware\Core\Framework\Filesystem\PrefixFilesystem;
use Shopware\Core\Kernel;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
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
    }

    public function boot(): void
    {
        $this->container->get(AssetPackageService::class)->addAssetPackage($this->getName());

        parent::boot();
    }

    public function getClassName(): string
    {
        return get_class($this);
    }

    /**
     * @return string[]
     */
    public function getViewPaths(): array
    {
        return [
            'Resources/views',
        ];
    }

    public function getAdministrationEntryPath(): string
    {
        return 'Resources/administration';
    }

    public function getStorefrontEntryPath(): string
    {
        return 'Resources/storefront';
    }

    public function getConfigPath(): string
    {
        return 'Resources/config';
    }

    public function getStorefrontScriptPath(): string
    {
        return 'Resources/dist/storefront/js';
    }

    public function getStorefrontStylePath(): string
    {
        return 'Resources/storefront/style';
    }

    public function getMigrationNamespace(): string
    {
        return $this->getNamespace() . '\Migration';
    }

    public function configureRoutes(RouteCollectionBuilder $routes, string $environment): void
    {
        $fileSystem = new Filesystem();
        $confDir = $this->getPath() . '/' . ltrim($this->getRoutesPath(), '/');

        if ($fileSystem->exists($confDir)) {
            $routes->import($confDir . '/{routes}/*' . Kernel::CONFIG_EXTS, '/', 'glob');
            $routes->import($confDir . '/{routes}/' . $environment . '/**/*' . Kernel::CONFIG_EXTS, '/', 'glob');
            $routes->import($confDir . '/{routes}' . Kernel::CONFIG_EXTS, '/', 'glob');
            $routes->import($confDir . '/{routes}_' . $environment . Kernel::CONFIG_EXTS, '/', 'glob');
        }
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

    protected function getRoutesPath(): string
    {
        return 'Resources/config';
    }

    protected function getServicesFilePath(): string
    {
        return 'Resources/config/services.xml';
    }

    protected function registerFilesystem(ContainerBuilder $container, string $key, ?string $baseKey = null): void
    {
        $containerPrefix = $this->getContainerPrefix();
        $parameterKey = sprintf('shopware.filesystem.%s', $baseKey ?? $key);
        $serviceId = sprintf('%s.filesystem.%s', $containerPrefix, $key);
        $suffix = empty($baseKey) ? '' : '-' . $key;

        $filesystem = new Definition(
            PrefixFilesystem::class,
            [
                new Reference($parameterKey),
                'plugins/' . $containerPrefix . $suffix,
            ]
        );

        $container->setDefinition($serviceId, $filesystem);
    }

    protected function getContainerPrefix(): string
    {
        return (new CamelCaseToSnakeCaseNameConverter())->normalize($this->getName());
    }

    protected function registerMigrationPath(ContainerBuilder $container): void
    {
        $migrationPath = $this->getMigrationPath();

        if (!is_dir($migrationPath)) {
            return;
        }

        $directories = $container->getParameter('migration.directories');
        $directories[$this->getMigrationNamespace()] = $migrationPath;

        $container->setParameter('migration.directories', $directories);
    }

    protected function registerEvents(ContainerBuilder $container): void
    {
        $definition = $container->getDefinition(BusinessEventRegistry::class);
        $definition->addMethodCall('addMultiple', [$this->getActionEvents()]);
    }

    protected function getActionEvents(): array
    {
        return [];
    }

    protected function registerContainerFile(ContainerBuilder $container): void
    {
        $fileSystem = new Filesystem();
        $containerFilePath = ltrim($this->getServicesFilePath(), '/');
        if (!$fileSystem->exists($this->getPath() . '/' . $containerFilePath)) {
            return;
        }

        $loader = new XmlFileLoader($container, new FileLocator($this->getPath()));
        $loader->load($containerFilePath);
    }
}
