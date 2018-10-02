<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Framework\Filesystem\PrefixFilesystem;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\Kernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Routing\RouteCollectionBuilder;

class Plugin extends Bundle
{
    /**
     * @var bool
     */
    private $active;

    public function __construct(bool $active = true)
    {
        $this->active = $active;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @return BundleInterface[]
     */
    public function registerBundles(): array
    {
        return [];
    }

    /**
     * This method can be overridden
     *
     * @param InstallContext $context
     */
    public function install(InstallContext $context)
    {
    }

    public function postInstall(InstallContext $context): void
    {
    }

    /**
     * This method can be overridden
     *
     * @param UpdateContext $context
     */
    public function update(UpdateContext $context): void
    {
        $context->scheduleClearCache(InstallContext::CACHE_LIST_DEFAULT);
    }

    public function postUpdate(UpdateContext $context): void
    {
    }

    /**
     * This method can be overridden
     *
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context)
    {
        $context->scheduleClearCache(InstallContext::CACHE_LIST_DEFAULT);
    }

    /**
     * This method can be overridden
     *
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context)
    {
        $context->scheduleClearCache(InstallContext::CACHE_LIST_DEFAULT);
    }

    /**
     * This method can be overridden
     *
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context)
    {
        $context->scheduleClearCache(InstallContext::CACHE_LIST_DEFAULT);
    }

    public function build(ContainerBuilder $container)
    {
        $this->registerFilesystem($container, 'private');
        $this->registerFilesystem($container, 'public');
        $this->registerMigrationPath($container);
    }

    public function configureRoutes(RouteCollectionBuilder $routes, string $environment): void
    {
        $confDir = $this->getPath() . '/Resources';

        $routes->import($confDir . '/{routes}/*' . Kernel::CONFIG_EXTS, '/', 'glob');
        $routes->import($confDir . '/{routes}/' . $environment . '/**/*' . Kernel::CONFIG_EXTS, '/', 'glob');
        $routes->import($confDir . '/{routes}' . Kernel::CONFIG_EXTS, '/', 'glob');
    }

    public function getMigrationNamespace(): string
    {
        return $this->getNamespace() . '\Migration';
    }

    public function getContainerPrefix(): string
    {
        return $this->camelCaseToUnderscore($this->getName());
    }

    /**
     * @param ContainerBuilder $container
     * @param string           $key
     */
    private function registerFilesystem(ContainerBuilder $container, string $key): void
    {
        $parameterKey = sprintf('shopware.filesystem.%s', $key);
        $serviceId = sprintf('%s.filesystem.%s', $this->getContainerPrefix(), $key);

        $filesystem = new Definition(
            PrefixFilesystem::class,
            [
                new Reference($parameterKey),
                'plugins/' . $this->getContainerPrefix(),
            ]
        );

        $container->setDefinition($serviceId, $filesystem);
    }

    private function camelCaseToUnderscore(string $string): string
    {
        return strtolower(ltrim(preg_replace('/[A-Z]/', '_$0', $string), '_'));
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
