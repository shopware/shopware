<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Shopware\Core\Framework\Filesystem\PrefixFilesystem;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Kernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Routing\RouteCollectionBuilder;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

abstract class Plugin extends Bundle
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var bool
     */
    private $active;

    public function __construct(bool $active = true, ?string $path = null)
    {
        $this->active = $active;
        $this->path = $path;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function install(InstallContext $context): void
    {
    }

    public function postInstall(InstallContext $context): void
    {
    }

    public function update(UpdateContext $context): void
    {
    }

    public function postUpdate(UpdateContext $context): void
    {
    }

    public function activate(ActivateContext $context): void
    {
    }

    public function deactivate(DeactivateContext $context): void
    {
    }

    public function uninstall(UninstallContext $context): void
    {
    }

    public function build(ContainerBuilder $container): void
    {
        $this->registerFilesystem($container, 'private');
        $this->registerFilesystem($container, 'public');
        $this->registerMigrationPath($container);
        $this->registerEvents($container);
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

    private function registerEvents(ContainerBuilder $container): void
    {
        $definition = $container->getDefinition(BusinessEventRegistry::class);
        $definition->addMethodCall('addMultiple', [$this->getActionEvents()]);
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

    private function registerMigrationPath(ContainerBuilder $container): void
    {
        $migrationPath = $this->getPath() . str_replace($this->getNamespace(), '', str_replace('\\', '/', $this->getMigrationNamespace()));

        if (!is_dir($migrationPath)) {
            return;
        }

        $directories = $container->getParameter('migration.directories');
        $directories[$this->getMigrationNamespace()] = $migrationPath;

        $container->setParameter('migration.directories', $directories);
    }
}
