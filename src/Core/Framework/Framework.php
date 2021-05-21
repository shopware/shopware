<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\ExtensionRegistry;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\ActionEventCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\AssetRegistrationCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\DefaultTransportCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\DisableTwigCacheWarmerCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\FeatureFlagCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\FilesystemConfigMigrationCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\RouteScopeCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\TwigLoaderConfigCompilerPass;
use Shopware\Core\Framework\DependencyInjection\FrameworkExtension;
use Shopware\Core\Framework\Migration\MigrationCompilerPass;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Kernel;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\DirectoryLoader;
use Symfony\Component\DependencyInjection\Loader\GlobFileLoader;
use Symfony\Component\DependencyInjection\Loader\IniFileLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class Framework extends Bundle
{
    public function getContainerExtension(): Extension
    {
        return new FrameworkExtension();
    }

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container): void
    {
        $container->setParameter('locale', 'en-GB');
        $environment = $container->getParameter('kernel.environment');

        $this->buildConfig($container, $environment);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('services.xml');
        $loader->load('acl.xml');
        $loader->load('api.xml');
        $loader->load('app.xml');
        $loader->load('custom-field.xml');
        $loader->load('data-abstraction-layer.xml');
        $loader->load('demodata.xml');
        $loader->load('event.xml');
        $loader->load('hydrator.xml');
        $loader->load('filesystem.xml');
        $loader->load('message-queue.xml');
        $loader->load('plugin.xml');
        $loader->load('rule.xml');
        $loader->load('scheduled-task.xml');
        $loader->load('store.xml');
        $loader->load('language.xml');
        $loader->load('update.xml');
        $loader->load('seo.xml');
        $loader->load('webhook.xml');

        if ($container->getParameter('kernel.environment') === 'test') {
            $loader->load('services_test.xml');
            $loader->load('store_test.xml');
        }

        // make sure to remove services behind a feature flag, before some other compiler passes may reference them, therefore the high priority
        $container->addCompilerPass(new FeatureFlagCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $container->addCompilerPass(new EntityCompilerPass());
        $container->addCompilerPass(new MigrationCompilerPass(), PassConfig::TYPE_AFTER_REMOVING);
        $container->addCompilerPass(new ActionEventCompilerPass());
        $container->addCompilerPass(new DisableTwigCacheWarmerCompilerPass());
        $container->addCompilerPass(new DefaultTransportCompilerPass());
        $container->addCompilerPass(new TwigLoaderConfigCompilerPass());
        $container->addCompilerPass(new RouteScopeCompilerPass());
        $container->addCompilerPass(new AssetRegistrationCompilerPass());
        $container->addCompilerPass(new FilesystemConfigMigrationCompilerPass());

        // configure migration directories
        $migrationSourceV3 = $container->getDefinition(MigrationSource::class . '.core.V6_3');
        $migrationSourceV3->addMethodCall('addDirectory', [__DIR__ . '/../Migration/V6_3', 'Shopware\Core\Migration\V6_3']);

        // we've moved the migrations from Shopware\Core\Migration to Shopware\Core\Migration\V6_3
        $migrationSourceV3->addMethodCall('addReplacementPattern', ['#^(Shopware\\\\Core\\\\Migration\\\\)V6_3\\\\([^\\\\]*)$#', '$1$2']);

        $migrationSourceV4 = $container->getDefinition(MigrationSource::class . '.core.V6_4');
        $migrationSourceV4->addMethodCall('addDirectory', [__DIR__ . '/../Migration/V6_4', 'Shopware\Core\Migration\V6_4']);
        $migrationSourceV3->addMethodCall('addReplacementPattern', ['#^(Shopware\\\\Core\\\\Migration\\\\)V6_4\\\\([^\\\\]*)$#', '$1$2']);

        $migrationSourceV5 = $container->getDefinition(MigrationSource::class . '.core.V6_5');
        $migrationSourceV5->addMethodCall('addDirectory', [__DIR__ . '/../Migration/V6_5', 'Shopware\Core\Migration\V6_5']);

        parent::build($container);
    }

    public function boot(): void
    {
        parent::boot();

        $featureFlags = $this->container->getParameter('shopware.feature.flags');
        if (!\is_array($featureFlags)) {
            throw new \RuntimeException('Container parameter "shopware.feature.flags" needs to be an array');
        }

        $cacheDir = $this->container->getParameter('kernel.cache_dir');
        if (!\is_string($cacheDir)) {
            throw new \RuntimeException('Container parameter "kernel.cache_dir" needs to be a string');
        }

        Feature::registerFeatures($featureFlags, $cacheDir . '/shopware_features.php');

        $this->registerEntityExtensions(
            $this->container->get(DefinitionInstanceRegistry::class),
            $this->container->get(SalesChannelDefinitionInstanceRegistry::class),
            $this->container->get(ExtensionRegistry::class)
        );
    }

    protected function getCoreMigrationPaths(): array
    {
        return [
            __DIR__ . '/../Migration' => 'Shopware\Core\Migration',
        ];
    }

    private function buildConfig(ContainerBuilder $container, $environment): void
    {
        $cacheDir = $container->getParameter('kernel.cache_dir');
        if (!\is_string($cacheDir)) {
            throw new \RuntimeException('Container parameter "kernel.cache_dir" needs to be a string');
        }

        $locator = new FileLocator('Resources/config');

        $resolver = new LoaderResolver([
            new XmlFileLoader($container, $locator),
            new YamlFileLoader($container, $locator),
            new IniFileLoader($container, $locator),
            new PhpFileLoader($container, $locator),
            new GlobFileLoader($container, $locator),
            new DirectoryLoader($container, $locator),
            new ClosureLoader($container),
        ]);

        $configLoader = new DelegatingLoader($resolver);

        $confDir = $this->getPath() . '/Resources/config';

        $configLoader->load($confDir . '/{packages}/*' . Kernel::CONFIG_EXTS, 'glob');
        $configLoader->load($confDir . '/{packages}/' . $environment . '/*' . Kernel::CONFIG_EXTS, 'glob');
        if ($environment === 'e2e') {
            $configLoader->load($confDir . '/{packages}/prod/*' . Kernel::CONFIG_EXTS, 'glob');
        }
        $shopwareFeaturesPath = $cacheDir . '/shopware_features.php';
        if (is_readable($shopwareFeaturesPath)) {
            $configLoader->load($shopwareFeaturesPath, 'php');
        }
    }

    private function registerEntityExtensions(
        DefinitionInstanceRegistry $definitionRegistry,
        SalesChannelDefinitionInstanceRegistry $salesChannelRegistry,
        ExtensionRegistry $registry
    ): void {
        foreach ($registry->getExtensions() as $extension) {
            /** @var string $class */
            $class = $extension->getDefinitionClass();

            $definition = $definitionRegistry->get($class);

            $definition->addExtension($extension);

            $salesChannelDefinition = $salesChannelRegistry->get($class);

            // same definition? do not added extension
            if ($salesChannelDefinition !== $definition) {
                $salesChannelDefinition->addExtension($extension);
            }
        }
    }
}
