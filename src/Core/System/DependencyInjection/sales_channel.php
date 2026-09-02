<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute;
use Shopware\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\Route\ApiRouteInfoResolver;
use Shopware\Core\Framework\App\Context\Gateway\AppContextGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Gateway\Context\Command\Executor\ContextGatewayCommandExecutor;
use Shopware\Core\Framework\Gateway\Context\Command\Executor\ContextGatewayCommandValidator;
use Shopware\Core\Framework\Gateway\Context\Command\Handler\AddCustomerMessageCommandHandler;
use Shopware\Core\Framework\Gateway\Context\Command\Handler\ChangeAddressCommandHandler;
use Shopware\Core\Framework\Gateway\Context\Command\Handler\ChangeCheckoutOptionsCommandHandler;
use Shopware\Core\Framework\Gateway\Context\Command\Handler\ChangeCurrencyCommandHandler;
use Shopware\Core\Framework\Gateway\Context\Command\Handler\ChangeLanguageCommandHandler;
use Shopware\Core\Framework\Gateway\Context\Command\Handler\ChangeShippingLocationCommandHandler;
use Shopware\Core\Framework\Gateway\Context\Command\Handler\LoginCustomerCommandHandler;
use Shopware\Core\Framework\Gateway\Context\Command\Handler\RegisterCustomerCommandHandler;
use Shopware\Core\Framework\Gateway\Context\Command\Registry\ContextGatewayCommandRegistry;
use Shopware\Core\Framework\Gateway\Context\SalesChannel\ContextGatewayRoute;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCountry\SalesChannelCountryDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCurrency\SalesChannelCurrencyDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelLanguage\SalesChannelLanguageDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelPaymentMethod\SalesChannelPaymentMethodDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelShippingMethod\SalesChannelShippingMethodDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation\SalesChannelTranslationDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTypeTranslation\SalesChannelTypeTranslationDefinition;
use Shopware\Core\System\SalesChannel\Api\StoreApiResponseListener;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;
use Shopware\Core\System\SalesChannel\Context\BaseSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\CachedBaseSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\CartRestorer;
use Shopware\Core\System\SalesChannel\Context\Cleanup\CleanupSalesChannelContextTask;
use Shopware\Core\System\SalesChannel\Context\Cleanup\CleanupSalesChannelContextTaskHandler;
use Shopware\Core\System\SalesChannel\Context\ContextFactory;
use Shopware\Core\System\SalesChannel\Context\InvalidationRaceAwareCache;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRequestRestorer;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextValueResolver;
use Shopware\Core\System\SalesChannel\Cookie\AnalyticsCookieCollectListener;
use Shopware\Core\System\SalesChannel\DataAbstractionLayer\SalesChannelIndexer;
use Shopware\Core\System\SalesChannel\Entity\DefinitionRegistryChain;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\File\Api\SalesChannelFileAdministrationReader;
use Shopware\Core\System\SalesChannel\File\Api\SalesChannelFileController;
use Shopware\Core\System\SalesChannel\File\Discovery\SalesChannelFileDiscovery;
use Shopware\Core\System\SalesChannel\File\Loader\SalesChannelFileConfigurationLoader;
use Shopware\Core\System\SalesChannel\File\Loader\SalesChannelFileLoader;
use Shopware\Core\System\SalesChannel\File\Rendering\SalesChannelFileRenderer;
use Shopware\Core\System\SalesChannel\File\Rendering\SalesChannelFileStoreApiMcpSubscriber;
use Shopware\Core\System\SalesChannel\File\Rendering\SalesChannelFileTemplateOverrideLoader;
use Shopware\Core\System\SalesChannel\File\SalesChannelFileCacheInvalidator;
use Shopware\Core\System\SalesChannel\File\SalesChannelFileNotFoundSubscriber;
use Shopware\Core\System\SalesChannel\File\SalesChannelFileRequestPathResolver;
use Shopware\Core\System\SalesChannel\File\SalesChannelFileTemplateResolver;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextRoute;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannel\SalesChannelContextSwitcher;
use Shopware\Core\System\SalesChannel\SalesChannel\StoreApiInfoController;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelExceptionHandler;
use Shopware\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Shopware\Core\System\SalesChannel\Subscriber\SalesChannelMaintenanceIpAllowlistSyncSubscriber;
use Shopware\Core\System\SalesChannel\Subscriber\SalesChannelTypeValidator;
use Shopware\Core\System\SalesChannel\Telemetry\SalesChannelTypeResolver;
use Shopware\Core\System\SalesChannel\Validation\SalesChannelValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SalesChannelDefinition::class)
        ->tag('shopware.entity.definition')
        ->tag('shopware.entity.hookable');

    $services->set(SalesChannelTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SalesChannelCountryDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SalesChannelCurrencyDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SalesChannelDomainDefinition::class)
        ->tag('shopware.entity.definition')
        ->tag('shopware.entity.hookable');

    $services->set(SalesChannelLanguageDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SalesChannelPaymentMethodDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SalesChannelShippingMethodDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SalesChannelTypeDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SalesChannelTypeTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SalesChannelAnalyticsDefinition::class)
        ->tag('shopware.entity.definition', ['entity' => 'sales_channel_analytics']);

    $services->set(SalesChannelFileDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SalesChannelFileTemplateOverrideLoader::class)
        ->tag('twig.loader', ['priority' => 100])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(SalesChannelFileDiscovery::class)
        ->public()
        ->args([
            service('twig.template_iterator'),
            service('cache.object'),
        ]);

    $services->set(SalesChannelFileConfigurationLoader::class)
        ->args([
            service('sales_channel_file.repository'),
        ]);

    $services->set(SalesChannelFileTemplateResolver::class)
        ->args([
            service(TemplateFinder::class),
            service(NamespaceHierarchyBuilder::class),
            service('twig.loader'),
            service('event_dispatcher'),
        ]);

    $services->set(SalesChannelFileAdministrationReader::class)
        ->args([
            service(SalesChannelFileDiscovery::class),
            service(SalesChannelFileConfigurationLoader::class),
            service('twig'),
            service(SalesChannelFileTemplateResolver::class),
        ]);

    $services->set(SalesChannelFileRequestPathResolver::class);

    $services->set(SalesChannelFileRenderer::class)
        ->args([
            service('twig'),
            service(SalesChannelFileTemplateResolver::class),
            service(SalesChannelFileTemplateOverrideLoader::class),
            service(SeoUrlPlaceholderHandlerInterface::class),
            service('sales_channel.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(SalesChannelFileStoreApiMcpSubscriber::class)
        ->args([
            service('router'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SalesChannelFileLoader::class)
        ->public()
        ->args([
            service(SalesChannelFileDiscovery::class),
            service(SalesChannelFileConfigurationLoader::class),
            service(SalesChannelFileRenderer::class),
            service(CacheTagCollector::class),
        ]);

    $services->set(SalesChannelFileCacheInvalidator::class)
        ->args([
            service(CacheInvalidator::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SalesChannelFileNotFoundSubscriber::class)
        ->args([
            service(SalesChannelFileLoader::class),
            service(SalesChannelFileRequestPathResolver::class),
            service(SalesChannelContextRequestRestorer::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SalesChannelFileController::class)
        ->public()
        ->args([
            service(SalesChannelFileAdministrationReader::class),
            service(SalesChannelFileLoader::class),
            service(SalesChannelContextFactory::class),
            service(SalesChannelFileRequestPathResolver::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(SalesChannelContextPersister::class)
        ->args([
            service(Connection::class),
            service('event_dispatcher'),
            service(CartPersister::class),
            service(ClockInterface::class),
            param('shopware.api.store.context_lifetime'),
        ]);

    $services->set(SalesChannelContextRequestRestorer::class)
        ->args([
            service(SalesChannelContextService::class),
        ]);

    $services->set(SalesChannelContextFactory::class)
        ->public()
        ->args([
            service('customer.repository'),
            service('customer_group.repository'),
            service('customer_address.repository'),
            service('payment_method.repository'),
            service(TaxDetector::class),
            tagged_iterator('tax.rule_type_filter'),
            service('event_dispatcher'),
            service('currency_country_rounding.repository'),
            service(BaseSalesChannelContextFactory::class),
        ]);

    $services->set(BaseSalesChannelContextFactory::class)
        ->args([
            service('sales_channel.repository'),
            service('customer_group.repository'),
            service('country.repository'),
            service('tax.repository'),
            service('payment_method.repository'),
            service('shipping_method.repository'),
            service('country_state.repository'),
            service('currency_country_rounding.repository'),
            service(ContextFactory::class),
            service('language.repository'),
        ]);

    $services->set(ContextFactory::class)
        ->args([
            service(Connection::class),
            service('event_dispatcher'),
        ]);

    $services->set(CachedBaseSalesChannelContextFactory::class)
        ->decorate(BaseSalesChannelContextFactory::class)
        ->args([
            service(CachedBaseSalesChannelContextFactory::class . '.inner'),
            service(InvalidationRaceAwareCache::class),
        ]);

    $services->set(InvalidationRaceAwareCache::class)
        ->args([
            service('cache.object'),
        ]);

    $services->set(CachedSalesChannelContextFactory::class)
        ->decorate(SalesChannelContextFactory::class, null, -1000)
        ->public()
        ->args([
            service(CachedSalesChannelContextFactory::class . '.inner'),
            service(InvalidationRaceAwareCache::class),
        ]);

    $services->set(SalesChannelContextService::class)
        ->args([
            service(SalesChannelContextFactory::class),
            service(CartRuleLoader::class),
            service(SalesChannelContextPersister::class),
            service(CartService::class),
            service('event_dispatcher'),
            service(RequestStack::class),
        ]);

    $services->set(SalesChannelContextRestorer::class)
        ->args([
            service(SalesChannelContextFactory::class),
            service(CartRuleLoader::class),
            service(OrderConverter::class),
            service('order.repository'),
            service(Connection::class),
            service('event_dispatcher'),
        ]);

    $services->set(CartRestorer::class)
        ->args([
            service(SalesChannelContextFactory::class),
            service(SalesChannelContextPersister::class),
            service(CartService::class),
            service(CartRuleLoader::class),
            service(CartPersister::class),
            service('event_dispatcher'),
            service(RequestStack::class),
        ]);

    $services->set(StoreApiInfoController::class)
        ->public()
        ->args([
            service(DefinitionService::class),
            service('twig'),
            param('shopware.security.csp_templates'),
            service(ApiRouteInfoResolver::class),
        ]);

    $services->set(SalesChannelContextSwitcher::class)
        ->args([
            service(ContextSwitchRoute::class),
        ]);

    $services->set(ContextSwitchRoute::class)
        ->public()
        ->args([
            service(DataValidator::class),
            service(SalesChannelContextPersister::class),
            service('event_dispatcher'),
            service(SalesChannelContextService::class),
        ]);

    $services->set(ContextRoute::class)
        ->public();

    $services->set(SalesChannelDefinitionInstanceRegistry::class)
        ->public()
        ->args([
            '',
            service('service_container'),
            [],
            [],
        ]);

    $services->set(DefinitionRegistryChain::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(SalesChannelDefinitionInstanceRegistry::class),
        ]);

    $services->set(SalesChannelContextValueResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => 1000]);

    $services->set(SalesChannelExceptionHandler::class)
        ->tag('shopware.dal.exception_handler');

    $services->set(StoreApiResponseListener::class)
        ->tag('kernel.event_subscriber')
        ->args([
            service(StructEncoder::class),
            service('event_dispatcher'),
            service(SeoUrlPlaceholderHandlerInterface::class),
            service(MediaUrlPlaceholderHandlerInterface::class),
        ]);

    $services->set(StructEncoder::class)
        ->args([
            service(DefinitionRegistryChain::class),
            service('serializer'),
            service(Connection::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(SalesChannelIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('sales_channel.repository'),
            service('event_dispatcher'),
            service(ManyToManyIdFieldUpdater::class),
        ])
        ->tag('shopware.entity_indexer');

    $services->set(CleanupSalesChannelContextTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(CleanupSalesChannelContextTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(Connection::class),
            param('shopware.sales_channel_context.expire_days'),
            service(ClockInterface::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(SalesChannelValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SalesChannelTypeValidator::class)
        ->tag('kernel.event_subscriber');

    $services->set(AnalyticsCookieCollectListener::class)
        ->args([
            service('sales_channel_analytics.repository'),
        ])
        ->tag('kernel.event_listener');

    $services->set(StoreApiCustomFieldMapper::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    // Context Gateway
    $services->set(ContextGatewayRoute::class)
        ->public()
        ->args([
            service(AppContextGateway::class),
        ]);

    $services->set(ContextGatewayCommandValidator::class)
        ->args([
            service(ExceptionLogger::class),
        ]);

    $services->set(ContextGatewayCommandExecutor::class)
        ->args([
            service(ContextSwitchRoute::class),
            service(ContextGatewayCommandRegistry::class),
            service(ContextGatewayCommandValidator::class),
            service(ExceptionLogger::class),
            service(SalesChannelContextService::class),
        ]);

    $services->set(ContextGatewayCommandRegistry::class)
        ->args([
            tagged_iterator('shopware.context.gateway.command'),
        ]);

    $services->set(AddCustomerMessageCommandHandler::class)
        ->tag('shopware.context.gateway.command');

    $services->set(ChangeAddressCommandHandler::class)
        ->tag('shopware.context.gateway.command');

    $services->set(ChangeCheckoutOptionsCommandHandler::class)
        ->args([
            service('payment_method.repository'),
            service('shipping_method.repository'),
        ])
        ->tag('shopware.context.gateway.command');

    $services->set(ChangeCurrencyCommandHandler::class)
        ->args([
            service('currency.repository'),
        ])
        ->tag('shopware.context.gateway.command');

    $services->set(ChangeLanguageCommandHandler::class)
        ->args([
            service('language.repository'),
        ])
        ->tag('shopware.context.gateway.command');

    $services->set(ChangeShippingLocationCommandHandler::class)
        ->args([
            service('country.repository'),
            service('country_state.repository'),
        ])
        ->tag('shopware.context.gateway.command');

    $services->set(LoginCustomerCommandHandler::class)
        ->args([
            service(AccountService::class),
        ])
        ->tag('shopware.context.gateway.command');

    $services->set(RegisterCustomerCommandHandler::class)
        ->args([
            service(RegisterRoute::class),
        ])
        ->tag('shopware.context.gateway.command');

    $services->set(SalesChannelMaintenanceIpAllowlistSyncSubscriber::class)
        ->tag('kernel.event_subscriber');

    // Telemetry: shared sales_channel_type label resolver (cart calculation, order placed metrics)
    $services->set(SalesChannelTypeResolver::class);
};
