<?php declare(strict_types=1);

namespace Shopware\Core\Framework;

use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\ReverseProxyCompilerPass;
use Shopware\Core\Framework\Adapter\Redis\RedisConnectionsCompilerPass;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityCompiler;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityExtension;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\DefinitionNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\ExtensionRegistry;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\AssetBundleRegistrationCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\AssetRegistrationCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\AttributeEntityCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\AutoconfigureCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\CreateGeneratorScaffoldingCommandPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\DefaultTransportCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\DemodataCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\DisableTwigCacheWarmerCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\FeatureFlagCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\FilesystemConfigMigrationCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\FrameworkMigrationReplacementCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\HttpCacheConfigCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\RateLimiterCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\RedisPrefixCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\RouteScopeCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\TwigEnvironmentCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\TwigLoaderConfigCompilerPass;
use Shopware\Core\Framework\DependencyInjection\FrameworkExtension;
use Shopware\Core\Framework\Feature\FeatureFlagRegistry;
use Shopware\Core\Framework\Increment\IncrementerGatewayCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\MessageHandlerCompilerPass;
use Shopware\Core\Framework\Telemetry\Metrics\MeterProvider;
use Shopware\Core\Framework\Test\DependencyInjection\CompilerPass\ContainerVisibilityCompilerPass;
use Shopware\Core\Framework\Test\RateLimiter\DisableRateLimiterCompilerPass;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

/**
 * @internal
 */
#[Package('core')]
class Framework extends Bundle
{
    public function getTemplatePriority(): int
    {
        return -1;
    }

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

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('services.xml');
        $loader->load('acl.xml');
        $loader->load('cache.xml');
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
        $loader->load('script.xml');
        $loader->load('language.xml');
        $loader->load('update.xml');
        $loader->load('seo.xml');
        $loader->load('webhook.xml');
        $loader->load('rate-limiter.xml');
        $loader->load('increment.xml');
        $loader->load('flag.xml');
        $loader->load('health.xml');
        $loader->load('telemetry.xml');

        if ($container->getParameter('kernel.environment') === 'test') {
            $loader->load('services_test.xml');
            $loader->load('store_test.xml');
            $loader->load('seo_test.xml');
            $loader->load('app_test.xml');
        }

        // make sure to remove services behind a feature flag, before some other compiler passes may reference them, therefore the high priority
        $container->addCompilerPass(new AttributeEntityCompilerPass(new AttributeEntityCompiler()), PassConfig::TYPE_BEFORE_REMOVING, 1000);
        $container->addCompilerPass(new FeatureFlagCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $container->addCompilerPass(new EntityCompilerPass());
        $container->addCompilerPass(new DisableTwigCacheWarmerCompilerPass());
        $container->addCompilerPass(new DefaultTransportCompilerPass());
        $container->addCompilerPass(new TwigLoaderConfigCompilerPass());
        $container->addCompilerPass(new TwigEnvironmentCompilerPass());
        $container->addCompilerPass(new RouteScopeCompilerPass());
        $container->addCompilerPass(new AssetRegistrationCompilerPass());
        $container->addCompilerPass(new AssetBundleRegistrationCompilerPass());
        $container->addCompilerPass(new FilesystemConfigMigrationCompilerPass());
        $container->addCompilerPass(new RateLimiterCompilerPass());
        $container->addCompilerPass(new IncrementerGatewayCompilerPass());
        $container->addCompilerPass(new ReverseProxyCompilerPass());
        $container->addCompilerPass(new RedisPrefixCompilerPass(), PassConfig::TYPE_BEFORE_REMOVING, 0);
        $container->addCompilerPass(new AutoconfigureCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $container->addCompilerPass(new HttpCacheConfigCompilerPass());
        $container->addCompilerPass(new MessageHandlerCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $container->addCompilerPass(new CreateGeneratorScaffoldingCommandPass());
        $container->addCompilerPass(new RedisConnectionsCompilerPass());

        if ($container->getParameter('kernel.environment') === 'test') {
            $container->addCompilerPass(new DisableRateLimiterCompilerPass());
            $container->addCompilerPass(new ContainerVisibilityCompilerPass());
        }

        $container->addCompilerPass(new FrameworkMigrationReplacementCompilerPass());

        $container->addCompilerPass(new DemodataCompilerPass());

        parent::build($container);
        $this->buildDefaultConfig($container);
    }

    public function boot(): void
    {
        parent::boot();

        \assert($this->container instanceof ContainerInterface, 'Container is not set yet, please call setContainer() before calling boot(), see `src/Core/Kernel.php:186`.');

        /** @var FeatureFlagRegistry $featureFlagRegistry */
        $featureFlagRegistry = $this->container->get(FeatureFlagRegistry::class);
        $featureFlagRegistry->register();
        // Inject the meter early in the application lifecycle. This is needed to use the meter in special case (static contexts).
        MeterProvider::bindMeter($this->container);

        $this->registerEntityExtensions(
            $this->container->get(DefinitionInstanceRegistry::class),
            $this->container->get(SalesChannelDefinitionInstanceRegistry::class),
            $this->container->get(ExtensionRegistry::class)
        );

        \assert($this->container instanceof ContainerInterface, 'Container is not set yet, please call setContainer() before calling boot(), see `src/Core/Kernel.php:186`.');

        CacheValueCompressor::$compress = $this->container->getParameter('shopware.cache.cache_compression');
        CacheValueCompressor::$compressMethod = $this->container->getParameter('shopware.cache.cache_compression_method');
    }

    private function registerEntityExtensions(
        DefinitionInstanceRegistry $definitionRegistry,
        SalesChannelDefinitionInstanceRegistry $salesChannelRegistry,
        ExtensionRegistry $registry
    ): void {
        foreach ($registry->getExtensions() as $extension) {
            $this->addExtension($definitionRegistry, $salesChannelRegistry, $extension);
        }

        foreach ($registry->buildBulkExtensions($definitionRegistry) as $extension) {
            $this->addExtension($definitionRegistry, $salesChannelRegistry, $extension);
        }
    }

    private function addExtension(
        DefinitionInstanceRegistry $definitionRegistry,
        SalesChannelDefinitionInstanceRegistry $salesChannelRegistry,
        EntityExtension $extension
    ): void {
        try {
            $definition = $this->getInstance($definitionRegistry, $extension);
        } catch (DefinitionNotFoundException) {
            return;
        }

        $definition->addExtension($extension);

        try {
            $salesChannelDefinition = $this->getInstance($salesChannelRegistry, $extension);
        } catch (DefinitionNotFoundException) {
            return;
        }

        // same definition? do not added extension
        if ($salesChannelDefinition !== $definition) {
            $salesChannelDefinition->addExtension($extension);
        }
    }

    private function getInstance(DefinitionInstanceRegistry $registry, EntityExtension $extension): EntityDefinition
    {
        if (Feature::isActive('v6.7.0.0')) {
            $entity = $extension->getEntityName();

            return $registry->getByEntityName($entity);
        }

        if (!empty($extension->getEntityName())) {
            $entity = $extension->getEntityName();

            return $registry->getByEntityName($entity);
        }

        $class = $extension->getDefinitionClass();

        return $registry->get($class);
    }
}
