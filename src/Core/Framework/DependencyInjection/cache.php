<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Content\Category\Event\CategoryIndexerEvent;
use Shopware\Core\Content\LandingPage\Event\LandingPageIndexerEvent;
use Shopware\Core\Content\Media\Event\MediaIndexerEvent;
use Shopware\Core\Content\Product\Events\InvalidateProductCache;
use Shopware\Core\Content\Rule\Event\RuleIndexerEvent;
use Shopware\Core\Content\Sitemap\Event\SitemapGeneratedEvent;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollection;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheControlListener;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheHeadersService;
use Shopware\Core\Framework\Adapter\Cache\Http\CachePolicyProvider;
use Shopware\Core\Framework\Adapter\Cache\Http\CachePolicyProviderFactory;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheRelevantRulesResolver;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheResponseSubscriber;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheStore;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheTask;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheTaskHandler;
use Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\AbstractInvalidatorStorage;
use Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\MySQLInvalidatorStorage;
use Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\RedisInvalidatorStorage;
use Shopware\Core\Framework\Adapter\Cache\Message\CleanupOldCacheFoldersHandler;
use Shopware\Core\Framework\Adapter\Cache\Message\RefreshHttpCacheMessageHandler;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\AbstractReverseProxyGateway;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\FastlyReverseProxyGateway;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\ReverseProxyCache;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\VarnishReverseProxyGateway;
use Shopware\Core\Framework\Adapter\Cache\Script\Facade\CacheInvalidatorFacadeHookFactory;
use Shopware\Core\Framework\Adapter\Cache\Script\ScriptCacheInvalidationSubscriber;
use Shopware\Core\Framework\Adapter\Cache\StampedeProtectionConfigurator;
use Shopware\Core\Framework\Adapter\Cache\Telemetry\CacheTelemetrySubscriber;
use Shopware\Core\Framework\Adapter\Command\CacheClearAllCommand;
use Shopware\Core\Framework\Adapter\Command\CacheClearHttpCommand;
use Shopware\Core\Framework\Adapter\Command\CacheInvalidateDelayedCommand;
use Shopware\Core\Framework\Adapter\Kernel\EsiDecoration;
use Shopware\Core\Framework\Adapter\Redis\RedisConnectionProvider;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\BeforeSendResponseEvent;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Shopware\Core\Framework\Routing\MaintenanceModeResolver;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Util\Backtrace\BacktraceCollector;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedHook;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CacheStateSubscriber::class)
        ->args([
            service(CartService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(StampedeProtectionConfigurator::class)
        ->public()
        ->args([
            param('shopware.cache.disable_stampede_protection'),
        ]);

    $services->set('shopware.cache.invalidator.storage.redis_adapter', \Redis::class)
        ->public()
        ->factory([service(RedisConnectionProvider::class), 'getConnection'])
        ->args([
            param('shopware.cache.invalidation.delay_options.connection'),
        ]);

    $services->set('shopware.cache.invalidator.storage.redis', RedisInvalidatorStorage::class)
        ->lazy()
        ->args([
            service('shopware.cache.invalidator.storage.redis_adapter'),
            service('logger'),
        ])
        ->tag('shopware.cache.invalidator.storage', ['storage' => 'redis']);

    $services->set('shopware.cache.invalidator.storage.mysql', MySQLInvalidatorStorage::class)
        ->lazy()
        ->args([
            service(Connection::class),
            service('logger'),
        ])
        ->tag('shopware.cache.invalidator.storage', ['storage' => 'mysql']);

    $services->set('shopware.cache.invalidator.storage.locator', TaggedServiceLocator::class)
        ->args([
            tagged_locator('shopware.cache.invalidator.storage', 'storage'),
        ]);

    $services->set(AbstractInvalidatorStorage::class)
        ->factory([service('shopware.cache.invalidator.storage.locator'), 'get'])
        ->args([
            param('shopware.cache.invalidation.delay_options.storage'),
        ]);

    $services->set(CacheInvalidator::class)
        ->public()
        ->lazy()
        ->args([
            [
                service('cache.object'),
                service('cache.http'),
            ],
            service(AbstractInvalidatorStorage::class),
            service('event_dispatcher'),
            service(LoggerInterface::class),
            service('request_stack'),
            service('cache.http'),
            param('shopware.http_cache.soft_purge'),
            param('shopware.cache.invalidation.delay_enabled'),
            param('shopware.cache.invalidation.tag_invalidation_log_enabled'),
            service(BacktraceCollector::class),
            service(ClockInterface::class),
            service(AbstractReverseProxyGateway::class)->nullOnInvalid(),
        ]);

    $services->set(InvalidateCacheTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(InvalidateCacheTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(CacheInvalidator::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(CacheClearer::class)
        ->args([
            [
                'object' => service('cache.object'),
                'http' => service('cache.http'),
            ],
            service('cache_clearer'),
            service(AbstractReverseProxyGateway::class)->nullOnInvalid(),
            service(CacheInvalidator::class),
            service('filesystem'),
            param('kernel.cache_dir'),
            param('kernel.environment'),
            param('shopware.deployment.cluster_setup'),
            param('shopware.http_cache.reverse_proxy.enabled'),
            service('messenger.default_bus'),
            service('logger'),
            service('lock.factory'),
        ]);

    $services->set(CleanupOldCacheFoldersHandler::class)
        ->args([
            service(CacheClearer::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(RefreshHttpCacheMessageHandler::class)
        ->args([
            service('http_kernel.cache.inner'),
            service(CacheStore::class),
            service('cache.http'),
        ])
        ->tag('messenger.message_handler');

    $services->set(CacheInvalidatorFacadeHookFactory::class)
        ->public()
        ->args([
            service(CacheInvalidator::class),
        ]);

    $services->set(ScriptCacheInvalidationSubscriber::class)
        ->args([
            service(ScriptExecutor::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CacheInvalidationSubscriber::class)
        ->args([
            service(CacheInvalidator::class),
            service(Connection::class),
            param('shopware.product_stream.indexing'),
        ])
        ->tag('kernel.event_listener', ['event' => CategoryIndexerEvent::class, 'method' => 'invalidateCategoryRouteByCategoryIds', 'priority' => 2000])
        ->tag('kernel.event_listener', ['event' => LandingPageIndexerEvent::class, 'method' => 'invalidateIndexedLandingPages', 'priority' => 2000])
        ->tag('kernel.event_listener', ['event' => InvalidateProductCache::class, 'method' => 'invalidateProduct', 'priority' => 2001])
        ->tag('kernel.event_listener', ['event' => EntityDeleteEvent::class, 'method' => 'invalidateProductCrossSellingBeforeDeletion', 'priority' => 2001])
        ->tag('kernel.event_listener', ['event' => EntityWrittenContainerEvent::class, 'method' => 'invalidateCmsPageIds', 'priority' => 2001])
        ->tag('kernel.event_listener', ['event' => EntityWrittenContainerEvent::class, 'method' => 'invalidateCategoryRouteByCategoryTranslationChanges', 'priority' => 2001])
        ->tag('kernel.event_listener', ['event' => EntityWrittenContainerEvent::class, 'method' => 'invalidateProductCrossSelling', 'priority' => 2001])
        ->tag('kernel.event_listener', ['event' => EntityWrittenContainerEvent::class, 'method' => 'invalidateCurrencyRoute', 'priority' => 2002])
        ->tag('kernel.event_listener', ['event' => EntityWrittenContainerEvent::class, 'method' => 'invalidateLanguageRoute', 'priority' => 2003])
        ->tag('kernel.event_listener', ['event' => EntityWrittenContainerEvent::class, 'method' => 'invalidateNavigationRoute', 'priority' => 2004])
        ->tag('kernel.event_listener', ['event' => EntityWrittenContainerEvent::class, 'method' => 'invalidatePaymentMethodRoute', 'priority' => 2005])
        ->tag('kernel.event_listener', ['event' => EntityWrittenContainerEvent::class, 'method' => 'invalidateManufacturerFilters', 'priority' => 2007])
        ->tag('kernel.event_listener', ['event' => EntityWrittenContainerEvent::class, 'method' => 'invalidatePropertyFilters', 'priority' => 2008])
        ->tag('kernel.event_listener', ['event' => EntityWrittenContainerEvent::class, 'method' => 'invalidateContext', 'priority' => 2010])
        ->tag('kernel.event_listener', ['event' => EntityWrittenContainerEvent::class, 'method' => 'invalidateShippingMethodRoute', 'priority' => 2011])
        ->tag('kernel.event_listener', ['event' => EntityWrittenContainerEvent::class, 'method' => 'invalidateSnippets', 'priority' => 2012])
        ->tag('kernel.event_listener', ['event' => EntityWrittenContainerEvent::class, 'method' => 'invalidateStreamsBeforeIndexing', 'priority' => 2013])
        ->tag('kernel.event_listener', ['event' => EntityWrittenContainerEvent::class, 'method' => 'invalidateStreamIds', 'priority' => 2014])
        ->tag('kernel.event_listener', ['event' => EntityWrittenContainerEvent::class, 'method' => 'invalidateCountryRoute', 'priority' => 2015])
        ->tag('kernel.event_listener', ['event' => EntityWrittenContainerEvent::class, 'method' => 'invalidateSalutationRoute', 'priority' => 2016])
        ->tag('kernel.event_listener', ['event' => EntityWrittenContainerEvent::class, 'method' => 'invalidateInitialStateIdLoader', 'priority' => 2017])
        ->tag('kernel.event_listener', ['event' => EntityWrittenContainerEvent::class, 'method' => 'invalidateCountryStateRoute', 'priority' => 2018])
        ->tag('kernel.event_listener', ['event' => RuleIndexerEvent::class, 'method' => 'invalidateRules', 'priority' => 2000])
        ->tag('kernel.event_listener', ['event' => PluginPostInstallEvent::class, 'method' => 'invalidateRules', 'priority' => 2000])
        ->tag('kernel.event_listener', ['event' => PluginPostInstallEvent::class, 'method' => 'invalidateConfig', 'priority' => 2001])
        ->tag('kernel.event_listener', ['event' => PluginPostActivateEvent::class, 'method' => 'invalidateRules', 'priority' => 2000])
        ->tag('kernel.event_listener', ['event' => PluginPostActivateEvent::class, 'method' => 'invalidateConfig', 'priority' => 2001])
        ->tag('kernel.event_listener', ['event' => PluginPostUpdateEvent::class, 'method' => 'invalidateRules', 'priority' => 2000])
        ->tag('kernel.event_listener', ['event' => PluginPostUpdateEvent::class, 'method' => 'invalidateConfig', 'priority' => 2001])
        ->tag('kernel.event_listener', ['event' => PluginPostDeactivateEvent::class, 'method' => 'invalidateRules', 'priority' => 2000])
        ->tag('kernel.event_listener', ['event' => PluginPostDeactivateEvent::class, 'method' => 'invalidateConfig', 'priority' => 2001])
        ->tag('kernel.event_listener', ['event' => SystemConfigChangedHook::class, 'method' => 'invalidateConfigKey', 'priority' => 2000])
        ->tag('kernel.event_listener', ['event' => SitemapGeneratedEvent::class, 'method' => 'invalidateSitemap', 'priority' => 2000])
        ->tag('kernel.event_listener', ['event' => MediaIndexerEvent::class, 'method' => 'invalidateMedia', 'priority' => 2000]);

    $services->set(CacheTagCollector::class)
        ->args([
            service('request_stack'),
            service('event_dispatcher'),
        ])
        ->tag('kernel.event_listener');

    $services->set(CacheTagCollection::class);

    $services->set(CachePolicyProvider::class)
        ->factory([CachePolicyProviderFactory::class, 'create'])
        ->args([
            param('shopware.http_cache.policies'),
            param('shopware.http_cache.route_policies'),
            param('shopware.http_cache.default_policies'),
        ]);

    $services->set(CacheResponseSubscriber::class)
        ->args([
            service(CartService::class),
            param('shopware.http.cache.default_ttl'),
            param('shopware.http.cache.enabled'),
            service(MaintenanceModeResolver::class),
            param('shopware.http_cache.stale_while_revalidate'),
            param('shopware.http_cache.stale_if_error'),
            service(CacheHeadersService::class),
            service(CachePolicyProvider::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CacheHeadersService::class)
        ->args([
            service(ExtensionDispatcher::class),
            service(CacheRelevantRulesResolver::class),
            param('shopware.http_cache.cookies'),
            service('event_dispatcher'),
        ]);

    $services->set(CacheRelevantRulesResolver::class)
        ->args([
            service(ExtensionDispatcher::class),
        ]);

    $services->set('esi', EsiDecoration::class);

    $services->set(CacheControlListener::class)
        ->autoconfigure()
        ->autowire()
        ->args([
            param('shopware.http_cache.reverse_proxy.enabled'),
            service('event_dispatcher'),
        ])
        ->tag('kernel.event_listener', ['event' => BeforeSendResponseEvent::class]);

    $services->set(ReverseProxyCache::class)
        ->args([
            service(AbstractReverseProxyGateway::class),
            param('shopware.cache.invalidation.http_cache'),
            service(CacheTagCollector::class),
        ])
        ->tag('kernel.event_listener');

    $services->set(CacheInvalidateDelayedCommand::class)
        ->tag('console.command')
        ->args([
            service(CacheInvalidator::class),
        ]);

    $services->set(CacheClearAllCommand::class)
        ->args([
            service(CacheClearer::class),
            param('kernel.environment'),
            param('kernel.debug'),
        ])
        ->tag('console.command');

    $services->set(CacheClearHttpCommand::class)
        ->args([
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set('shopware.reverse_proxy.http_client', Client::class);

    $services->set(AbstractReverseProxyGateway::class, VarnishReverseProxyGateway::class)
        ->args([
            param('shopware.http_cache.reverse_proxy.hosts'),
            param('shopware.http_cache.reverse_proxy.max_parallel_invalidations'),
            service('shopware.reverse_proxy.http_client'),
            service('logger'),
        ]);

    $services->set(FastlyReverseProxyGateway::class)
        ->args([
            service('shopware.reverse_proxy.http_client'),
            param('shopware.http_cache.reverse_proxy.fastly.service_id'),
            param('shopware.http_cache.reverse_proxy.fastly.api_key'),
            param('shopware.http_cache.reverse_proxy.fastly.soft_purge'),
            param('shopware.http_cache.reverse_proxy.max_parallel_invalidations'),
            param('shopware.http_cache.reverse_proxy.fastly.tag_prefix'),
            param('shopware.http_cache.reverse_proxy.fastly.instance_tag'),
            env('APP_URL'),
            service('logger'),
        ]);

    $services->set(CacheTelemetrySubscriber::class)
        ->args([
            service(Meter::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('shopware.telemetry.subscriber');
};
