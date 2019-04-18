<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Shopware\Core\Framework\Filesystem\PrefixFilesystem;
use Shopware\Core\Framework\Twig\TemplateFinder;
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
        $templateFinder = $this->container->get(TemplateFinder::class);
        $templateFinder->addBundle($this);

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
        return 'Resources/config/config.xml';
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
        }
    }

    protected function getRoutesPath(): string
    {
        return 'Resources/config';
    }

    protected function getServicesFilePath(): string
    {
        return 'Resources/config/services.xml';
    }

    protected function registerFilesystem(ContainerBuilder $container, string $key): void
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

    protected function getContainerPrefix(): string
    {
        return (new CamelCaseToSnakeCaseNameConverter())->normalize($this->getName());
    }

    protected function registerMigrationPath(ContainerBuilder $container): void
    {
        $migrationSuffix = str_replace(
            $this->getNamespace(),
            '',
            $this->getMigrationNamespace()
        );

        $migrationPath = $this->getPath() . str_replace('\\', '/', $migrationSuffix);

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
