<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Framework\Asset\AssetPackageService;
use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Shopware\Core\Framework\Filesystem\PrefixFilesystem;
use Shopware\Core\Kernel;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\FileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\Bundle as SymfonyBundle;
use Symfony\Component\Routing\RouteCollectionBuilder;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

abstract class Bundle extends SymfonyBundle
{
    /**
     * Array of supported service definitions files, associated to their
     * respective FileLoader.
     * This is used to load service defintions.
     *
     * @see registerContainerFile
     */
    protected const SUPPORTED_CONTAINER_FILES = [
        'services.xml' => XmlFileLoader::class,
        'services.yml' => YamlFileLoader::class,
    ];

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

    protected function getActionEvents(): array
    {
        return [];
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
    }

    /**
     * Looks for service definition files inside the `Resources/config`
     * directory and loads the first one, it finds.
     *
     * @param ContainerBuilder $container
     */
    private function registerContainerFile(ContainerBuilder $container): void
    {
        $fileSystem = new Filesystem();

        foreach (self::SUPPORTED_CONTAINER_FILES as $path => $loaderClass) {
            $containerFilePath = 'Resources/config/' . $path;
            if (!$fileSystem->exists($this->getPath() . '/' . $containerFilePath)) {
                continue;
            }

            /** @var $loader FileLoader */
            $loader = new $loaderClass($container, new FileLocator($this->getPath()));
            $loader->load($containerFilePath);
        }
    }
}
