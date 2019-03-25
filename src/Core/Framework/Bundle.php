<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Shopware\Core\Framework\Filesystem\PrefixFilesystem;
use Shopware\Core\Kernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RouteCollectionBuilder;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

abstract class Bundle extends \Symfony\Component\HttpKernel\Bundle\Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $this->registerFilesystem($container, 'private');
        $this->registerFilesystem($container, 'public');
        $this->registerMigrationPath($container);
        $this->registerEvents($container);
    }

    public function getMigrationNamespace(): string
    {
        return $this->getNamespace() . '\Migration';
    }

    public function getContainerPrefix(): string
    {
        return (new CamelCaseToSnakeCaseNameConverter())->normalize($this->getName());
    }

    public function getActionEvents(): array
    {
        return [];
    }

    public function configureRoutes(RouteCollectionBuilder $routes, string $environment): void
    {
        $fileSystem = new Filesystem();
        $confDir = $this->getPath() . '/Resources';

        if ($fileSystem->exists($confDir)) {
            $routes->import($confDir . '/{routes}/*' . Kernel::CONFIG_EXTS, '/', 'glob');
            $routes->import($confDir . '/{routes}/' . $environment . '/**/*' . Kernel::CONFIG_EXTS, '/', 'glob');
            $routes->import($confDir . '/{routes}' . Kernel::CONFIG_EXTS, '/', 'glob');
        }
    }

    protected function registerEvents(ContainerBuilder $container): void
    {
        $definition = $container->getDefinition(BusinessEventRegistry::class);
        $definition->addMethodCall('addMultiple', [$this->getActionEvents()]);
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
}
