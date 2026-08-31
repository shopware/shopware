<?php declare(strict_types=1);

namespace Shopware\Storefront\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountNewsletterRecipientRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerGroupRegistrationSettingsRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerRecoveryIsExpiredRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ListAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LoadWishlistRoute;
use Shopware\Core\Checkout\Customer\Validation\AddressValidationFactory;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Category\Service\NavigationLoader;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Shopware\Core\Content\LandingPage\SalesChannel\LandingPageRoute;
use Shopware\Core\Content\Media\Cms\DefaultMediaResolver as CoreDefaultMediaResolver;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterUnsubscribeRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\ProductListRoute;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRoute;
use Shopware\Core\Content\Seo\HreflangLoaderInterface;
use Shopware\Core\Content\Seo\SeoResolver;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Sitemap\SalesChannel\SitemapRoute;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\App\Template\TemplateLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\Event\BeforeSendResponseEvent;
use Shopware\Core\Framework\Routing\MaintenanceModeResolver as CoreMaintenanceModeResolver;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Maintenance\SalesChannel\Service\SalesChannelCreator;
use Shopware\Core\System\Country\SalesChannel\CountryRoute;
use Shopware\Core\System\Country\SalesChannel\CountryStateRoute;
use Shopware\Core\System\Currency\SalesChannel\CurrencyRoute;
use Shopware\Core\System\Language\SalesChannel\LanguageRoute;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRequestRestorer;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\Salutation\AbstractSalutationsSorter;
use Shopware\Core\System\Salutation\SalesChannel\SalutationRoute;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopware\Storefront\Checkout\Customer\CustomerGroupSubscriber;
use Shopware\Storefront\Checkout\Payment\BlockedPaymentMethodSwitcher;
use Shopware\Storefront\Checkout\Shipping\BlockedShippingMethodSwitcher;
use Shopware\Storefront\Controller\ScriptController;
use Shopware\Storefront\Event\CartMergedSubscriber;
use Shopware\Storefront\Framework\AffiliateTracking\AffiliateTrackingListener;
use Shopware\Storefront\Framework\App\Template\IconTemplateLoader;
use Shopware\Storefront\Framework\Cache\CacheCookieEventSubscriber;
use Shopware\Storefront\Framework\Captcha\BasicCaptcha\BasicCaptchaGenerator;
use Shopware\Storefront\Framework\Command\SalesChannelCreateStorefrontCommand;
use Shopware\Storefront\Framework\Cookie\AppCookieProvider;
use Shopware\Storefront\Framework\Cookie\CookieProvider;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;
use Shopware\Storefront\Framework\Media\StorefrontMediaUploader;
use Shopware\Storefront\Framework\Media\StorefrontMediaValidatorRegistry;
use Shopware\Storefront\Framework\Media\Validator\StorefrontMediaDocumentValidator;
use Shopware\Storefront\Framework\Media\Validator\StorefrontMediaImageValidator;
use Shopware\Storefront\Framework\Routing\CachedDomainLoader;
use Shopware\Storefront\Framework\Routing\CachedDomainLoaderInvalidator;
use Shopware\Storefront\Framework\Routing\CanonicalLinkListener;
use Shopware\Storefront\Framework\Routing\ClearSiteDataListener;
use Shopware\Storefront\Framework\Routing\DomainLoader;
use Shopware\Storefront\Framework\Routing\DomainNotMappedListener;
use Shopware\Storefront\Framework\Routing\MaintenanceModeResolver;
use Shopware\Storefront\Framework\Routing\NotFound\NotFoundSubscriber;
use Shopware\Storefront\Framework\Routing\ProductListingPageOutOfRangeSubscriber;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Framework\Routing\ResponseHeaderListener;
use Shopware\Storefront\Framework\Routing\RobotsRouteScopeWhitelist;
use Shopware\Storefront\Framework\Routing\Router;
use Shopware\Storefront\Framework\Routing\StorefrontRouteEventSubscriber;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopware\Storefront\Framework\Routing\StorefrontSubscriber;
use Shopware\Storefront\Framework\Routing\StorybookRouteScopeAllowList;
use Shopware\Storefront\Framework\Routing\TemplateDataSubscriber;
use Shopware\Storefront\Framework\Script\Api\StorefrontScriptResponseFactoryFacadeHookFactory;
use Shopware\Storefront\Framework\Store\Subscriber\ExtensionThemeDetectionSubscriber;
use Shopware\Storefront\Framework\SystemCheck\ProductDetailReadinessCheck;
use Shopware\Storefront\Framework\SystemCheck\ProductListingReadinessCheck;
use Shopware\Storefront\Framework\SystemCheck\SalesChannelsReadinessCheck;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainProvider;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainUtil;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentRenderEventListener;
use Shopware\Storefront\Framework\Twig\ErrorTemplateResolver;
use Shopware\Storefront\Framework\Twig\Extension\ConfigExtension;
use Shopware\Storefront\Framework\Twig\Extension\IconCacheTwigFilter;
use Shopware\Storefront\Framework\Twig\Extension\UrlEncodingTwigFilter;
use Shopware\Storefront\Framework\Twig\IconExtension;
use Shopware\Storefront\Framework\Twig\TemplateConfigAccessor;
use Shopware\Storefront\Framework\Twig\TemplateDataExtension;
use Shopware\Storefront\Framework\Twig\ThumbnailExtension;
use Shopware\Storefront\Framework\Twig\TwigAppVariable;
use Shopware\Storefront\Framework\Twig\TwigDateRequestListener;
use Shopware\Storefront\Page\Account\CustomerGroupRegistration\CustomerGroupRegistrationPageLoader;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoader;
use Shopware\Storefront\Page\Account\Order\AccountOrderDetailPageLoader;
use Shopware\Storefront\Page\Account\Order\AccountOrderPageLoader;
use Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoader;
use Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoader;
use Shopware\Storefront\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoader;
use Shopware\Storefront\Page\Address\Detail\AddressDetailPageLoader;
use Shopware\Storefront\Page\Address\Listing\AddressListingPageLoader;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoader;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoader;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoader;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoader;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoader;
use Shopware\Storefront\Page\Cms\DefaultMediaResolver;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\LandingPage\LandingPageLoader;
use Shopware\Storefront\Page\Maintenance\MaintenancePageLoader;
use Shopware\Storefront\Page\Navigation\Error\ErrorPageLoader;
use Shopware\Storefront\Page\Navigation\NavigationPageLoader;
use Shopware\Storefront\Page\Newsletter\Subscribe\NewsletterSubscribePageLoader;
use Shopware\Storefront\Page\Product\Configurator\ProductPageConfiguratorLoader;
use Shopware\Storefront\Page\Product\ProductPageLoader;
use Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPageLoader;
use Shopware\Storefront\Page\Robots\Parser\RobotsDirectiveParser;
use Shopware\Storefront\Page\Robots\RobotsConfigChangeSubscriber;
use Shopware\Storefront\Page\Robots\RobotsPageLoader;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Shopware\Storefront\Page\Sitemap\SitemapPageLoader;
use Shopware\Storefront\Page\Suggest\SuggestPageLoader;
use Shopware\Storefront\Page\Wishlist\GuestWishlistPageLoader;
use Shopware\Storefront\Page\Wishlist\WishlistPageLoader;
use Shopware\Storefront\Pagelet\Captcha\BasicCaptchaPageletLoader;
use Shopware\Storefront\Pagelet\Country\CountryStateDataPageletLoader;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoader;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoader;
use Shopware\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoader;
use Shopware\Storefront\Pagelet\Newsletter\Account\NewsletterAccountPageletLoader;
use Shopware\Storefront\Pagelet\Wishlist\GuestWishlistPageletLoader;
use Shopware\Storefront\Storybook\StorybookService;
use Shopware\Storefront\Theme\DatabaseSalesChannelThemeLoader;
use Shopware\Storefront\Theme\Mail\MailThemeConfigSubscriber;
use Shopware\Storefront\Theme\Mail\MailThemeIdLoader;
use Shopware\Storefront\Theme\ResolvedConfigLoader;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\ThemeConfigValueAccessor;
use Shopware\Storefront\Theme\ThemeRuntimeConfigService;
use Shopware\Storefront\Theme\ThemeRuntimeConfigStorage;
use Shopware\Storefront\Theme\ThemeScripts;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('shopware.twig.app_variable.allowed_server_params', [
            'server_name',
            'request_uri',
            'app_url',
            'http_user_agent',
            'http_host',
            'server_name',
            'server_port',
            'redirect_url',
            'https',
            'forwarded',
            'host',
            'remote_addr',
            'http_x_forwarded_for',
            'http_x_forwarded_host',
            'http_x_forwarded_proto',
            'http_x_forwarded_port',
            'http_x_forwarded_prefix',
        ]);

    $services = $containerConfigurator->services();

    // Checkout
    $services->set(StorefrontCartFacade::class)
        ->args([
            service(CartService::class),
            service(BlockedShippingMethodSwitcher::class),
            service(BlockedPaymentMethodSwitcher::class),
            service(ContextSwitchRoute::class),
            service(CartCalculator::class),
            service(CartPersister::class),
            service(CheckoutGatewayRoute::class),
        ]);

    $services->set(CustomerGroupSubscriber::class)
        ->args([
            service('customer_group.repository'),
            service('seo_url.repository'),
            service('language.repository'),
            service(SeoUrlPersister::class),
            service('slugify'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(BlockedShippingMethodSwitcher::class)
        ->args([
            service(ShippingMethodRoute::class),
        ]);

    $services->set(BlockedPaymentMethodSwitcher::class)
        ->args([
            service(PaymentMethodRoute::class),
        ]);

    $services->set(CacheCookieEventSubscriber::class)
        ->args([
            service('session.factory'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(StorefrontScriptResponseFactoryFacadeHookFactory::class)
        ->public()
        ->args([
            service('router'),
            service(ScriptController::class),
        ]);

    $services->set(ExtensionThemeDetectionSubscriber::class)
        ->args([
            service('theme.repository'),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(CachedDomainLoader::class)
        ->decorate(DomainLoader::class, null, -1000)
        ->args([
            service(CachedDomainLoader::class . '.inner'),
            service('cache.object'),
            service('logger'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(CachedDomainLoaderInvalidator::class)
        ->args([
            service(CacheInvalidator::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(DomainLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(RequestTransformer::class)
        ->decorate(RequestTransformerInterface::class)
        ->public()
        ->args([
            service(RequestTransformer::class . '.inner'),
            service(SeoResolver::class),
            param('shopware.routing.registered_api_prefixes'),
            service(DomainLoader::class),
        ]);

    $services->set(Router::class)
        ->decorate('router')
        ->args([
            service(Router::class . '.inner'),
            service('request_stack'),
            param('storefront.router.allowed_routes'),
        ]);

    $services->set(MaintenanceModeResolver::class)
        ->args([
            service('request_stack'),
            service(CoreMaintenanceModeResolver::class),
        ]);

    $services->set(StorefrontRouteEventSubscriber::class)
        ->args([
            service('event_dispatcher'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(StorefrontRouteScope::class)
        ->tag('shopware.route_scope');

    $services->set(TemplateDataExtension::class)
        ->args([
            service('request_stack'),
            param('shopware.staging.storefront.show_banner'),
            service(Connection::class),
        ])
        ->tag('twig.extension');

    $services->set(TemplateConfigAccessor::class)
        ->args([
            service(SystemConfigService::class),
            service(ThemeConfigValueAccessor::class),
            service(ThemeScripts::class),
            param('kernel.environment'),
            tagged_iterator('shopware.asset', 'asset'),
        ]);

    $services->set(MailThemeConfigSubscriber::class)
        ->args([
            service(SalesChannelContextFactory::class),
            service(MailThemeIdLoader::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(MailThemeIdLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(ThemeConfigValueAccessor::class)
        ->args([
            service(ResolvedConfigLoader::class),
            service(CacheTagCollector::class),
            service(ThemeRuntimeConfigService::class),
        ]);

    $services->set(ConfigExtension::class)
        ->args([
            service(TemplateConfigAccessor::class),
        ])
        ->tag('twig.extension');

    $services->set(IconExtension::class)
        ->tag('twig.extension');

    $services->set(ThumbnailExtension::class)
        ->args([
            service(TemplateFinder::class),
        ])
        ->tag('twig.extension');

    $services->set(TwigDateRequestListener::class)
        ->args([
            service('service_container'),
        ])
        ->tag('kernel.event_listener', ['event' => 'kernel.request']);

    $services->set(ErrorTemplateResolver::class)
        ->args([
            service('twig'),
        ]);

    $services->set(UrlEncodingTwigFilter::class)
        ->tag('twig.extension');

    $services->set(IconCacheTwigFilter::class)
        ->tag('twig.extension');

    $services->set(StorefrontMediaUploader::class)
        ->args([
            service(MediaService::class),
            service(FileSaver::class),
            service(StorefrontMediaValidatorRegistry::class),
        ]);

    $services->set(StorefrontMediaValidatorRegistry::class)
        ->public()
        ->args([
            tagged_iterator('storefront.media.upload.validator'),
        ]);

    $services->set(StorefrontMediaImageValidator::class)
        ->tag('storefront.media.upload.validator');

    $services->set(StorefrontMediaDocumentValidator::class)
        ->tag('storefront.media.upload.validator');

    $services->set(StorefrontSubscriber::class)
        ->args([
            service('request_stack'),
            service('router'),
            service(MaintenanceModeResolver::class),
            service(SystemConfigService::class),
            service('event_dispatcher'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ProductListingPageOutOfRangeSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(TemplateDataSubscriber::class)
        ->args([
            service(HreflangLoaderInterface::class),
            service(ShopIdProvider::class),
            service(ActiveAppsLoader::class),
            service(ThemeRuntimeConfigService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CanonicalLinkListener::class)
        ->tag('kernel.event_listener', ['event' => BeforeSendResponseEvent::class]);

    $services->set(NotFoundSubscriber::class)
        ->args([
            service('http_kernel'),
            service(SalesChannelContextRequestRestorer::class),
            param('kernel.debug'),
            service('cache.object'),
            service(EntityCacheKeyGenerator::class),
            service(CacheInvalidator::class),
            service('event_dispatcher'),
            param('session.storage.options'),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(AffiliateTrackingListener::class)
        ->tag('kernel.event_subscriber');

    $services->set(NavigationPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(CategoryRoute::class),
            service(SeoUrlPlaceholderHandlerInterface::class),
            service(CategoryBreadcrumbBuilder::class),
        ]);

    $services->set(ErrorPageLoader::class)
        ->args([
            service(SalesChannelCmsPageLoader::class),
            service(GenericPageLoader::class),
            service('event_dispatcher'),
        ]);

    $services->set(MaintenancePageLoader::class)
        ->args([
            service(SalesChannelCmsPageLoader::class),
            service(GenericPageLoader::class),
            service('event_dispatcher'),
        ]);

    $services->set(LandingPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service(LandingPageRoute::class),
            service('event_dispatcher'),
        ]);

    $services->set(MenuOffcanvasPageletLoader::class)
        ->args([
            service('event_dispatcher'),
            service(NavigationLoader::class),
        ]);

    $services->set(BasicCaptchaPageletLoader::class)
        ->args([
            service('event_dispatcher'),
            service(BasicCaptchaGenerator::class),
            service(NavigationLoader::class),
        ]);

    $services->set(CountryStateDataPageletLoader::class)
        ->args([
            service(CountryStateRoute::class),
            service('event_dispatcher'),
        ]);

    $services->set(SuggestPageLoader::class)
        ->args([
            service('event_dispatcher'),
            service(ProductSuggestRoute::class),
            service(GenericPageLoader::class),
        ]);

    $services->set(HeaderPageletLoader::class)
        ->args([
            service('event_dispatcher'),
            service(CurrencyRoute::class),
            service(LanguageRoute::class),
            service(NavigationLoader::class),
        ]);

    $services->set(FooterPageletLoader::class)
        ->args([
            service('event_dispatcher'),
            service(NavigationLoader::class),
            service(PaymentMethodRoute::class),
            service(ShippingMethodRoute::class),
        ]);

    $services->set(GenericPageLoader::class)
        ->args([
            service(SystemConfigService::class),
            service('event_dispatcher'),
        ]);

    $services->set(SearchPageLoader::class)
        ->public()
        ->args([
            service(GenericPageLoader::class),
            service(ProductSearchRoute::class),
            service('event_dispatcher'),
            service(Translator::class),
        ]);

    $services->set(ProductPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(ProductDetailRoute::class),
            service('product_review.repository'),
            service(SystemConfigService::class),
            service(CategoryBreadcrumbBuilder::class),
        ]);

    $services->set(MinimalQuickViewPageLoader::class)
        ->args([
            service('event_dispatcher'),
            service(ProductDetailRoute::class),
        ]);

    $services->set(ProductPageConfiguratorLoader::class)
        ->decorate(ProductConfiguratorLoader::class)
        ->args([
            service(ProductPageConfiguratorLoader::class . '.inner'),
        ]);

    $services->set(CheckoutFinishPageLoader::class)
        ->args([
            service('event_dispatcher'),
            service(GenericPageLoader::class),
            service(OrderRoute::class),
            service(Translator::class),
            service(SystemConfigService::class),
        ]);

    $services->set(CheckoutConfirmPageLoader::class)
        ->args([
            service('event_dispatcher'),
            service(StorefrontCartFacade::class),
            service(GenericPageLoader::class),
            service(AddressValidationFactory::class),
            service(DataValidator::class),
            service(Translator::class),
        ]);

    $services->set(CheckoutCartPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(StorefrontCartFacade::class),
            service(CountryRoute::class),
            service(Translator::class),
        ]);

    $services->set(OffcanvasCartPageLoader::class)
        ->args([
            service('event_dispatcher'),
            service(StorefrontCartFacade::class),
            service(GenericPageLoader::class),
            service(ShippingMethodRoute::class),
        ]);

    $services->set(AccountProfilePageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(SalutationRoute::class),
            service(AbstractSalutationsSorter::class),
            service(Translator::class),
        ]);

    $services->set(AccountOverviewPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(OrderRoute::class),
            service(CustomerRoute::class),
            service(NewsletterAccountPageletLoader::class),
            service(Translator::class),
        ]);

    $services->set(AccountOrderPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(OrderRoute::class),
            service(Translator::class),
        ]);

    $services->set(AccountOrderDetailPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(OrderRoute::class),
        ]);

    $services->set(AccountEditOrderPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(OrderRoute::class),
            service(CheckoutGatewayRoute::class),
            service(OrderConverter::class),
            service(OrderService::class),
            service(Translator::class),
            service(CartService::class),
        ]);

    $services->set(AccountLoginPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(CountryRoute::class),
            service(SalutationRoute::class),
            service(AbstractSalutationsSorter::class),
            service(Translator::class),
        ]);

    $services->set(AccountRecoverPasswordPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
            service(CustomerRecoveryIsExpiredRoute::class),
        ]);

    $services->set(CustomerGroupRegistrationPageLoader::class)
        ->args([
            service(AccountLoginPageLoader::class),
            service(CustomerGroupRegistrationSettingsRoute::class),
            service('event_dispatcher'),
        ]);

    $services->set(CheckoutRegisterPageLoader::class)
        ->public()
        ->args([
            service(GenericPageLoader::class),
            service(ListAddressRoute::class),
            service('event_dispatcher'),
            service(CartService::class),
            service(SalutationRoute::class),
            service(CountryRoute::class),
            service(Translator::class),
            service(AbstractSalutationsSorter::class),
        ]);

    $services->set(NewsletterSubscribePageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
        ]);

    $services->set(NewsletterAccountPageletLoader::class)
        ->args([
            service('event_dispatcher'),
            service(NewsletterSubscribeRoute::class),
            service(NewsletterUnsubscribeRoute::class),
            service(AccountNewsletterRecipientRoute::class),
            service(Translator::class),
            service(SystemConfigService::class),
        ]);

    $services->set(AddressDetailPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service(CountryRoute::class),
            service(SalutationRoute::class),
            service('event_dispatcher'),
            service(ListAddressRoute::class),
            service(AbstractSalutationsSorter::class),
            service(Translator::class),
        ]);

    $services->set(AddressListingPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service(CountryRoute::class),
            service(SalutationRoute::class),
            service(ListAddressRoute::class),
            service('event_dispatcher'),
            service(CartService::class),
            service(Translator::class),
            service(AbstractSalutationsSorter::class),
        ]);

    $services->set(SitemapPageLoader::class)
        ->args([
            service('event_dispatcher'),
            service(SitemapRoute::class),
        ]);

    $services->set(DefaultMediaResolver::class)
        ->decorate(CoreDefaultMediaResolver::class)
        ->args([
            service(DefaultMediaResolver::class . '.inner'),
            service(Translator::class),
            service('assets.packages'),
        ]);

    $services->set(SalesChannelCreateStorefrontCommand::class)
        ->args([
            service('snippet_set.repository'),
            service(SalesChannelCreator::class),
        ])
        ->tag('console.command');

    // @deprecated tag:v6.8.0 Will be removed
    $services->set(CookieProviderInterface::class, CookieProvider::class)
        ->deprecate('shopware/storefront', '6.7.3.0', 'The %service_id% service will be removed in v6.8.0.0. Use the CookieGroupCollectEvent instead to introduce cookies.');

    // @deprecated tag:v6.8.0 Will be removed
    $services->set(AppCookieProvider::class)
        ->decorate(CookieProviderInterface::class)
        ->args([
            service('.inner'),
        ])
        ->deprecate('shopware/storefront', '6.7.3.0', 'The %service_id% service will be removed in v6.8.0.0 without replacement');

    $services->set(ResponseHeaderListener::class)
        ->tag('kernel.event_subscriber');

    $services->set(ClearSiteDataListener::class)
        ->args([
            param('storefront.security.clear_site_data_on_logout'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CartMergedSubscriber::class)
        ->args([
            service('translator'),
            service('request_stack'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(WishlistPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service(LoadWishlistRoute::class),
            service('event_dispatcher'),
        ]);

    $services->set(GuestWishlistPageLoader::class)
        ->args([
            service(GenericPageLoader::class),
            service('event_dispatcher'),
        ]);

    $services->set(GuestWishlistPageletLoader::class)
        ->args([
            service(ProductListRoute::class),
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service(ProductCloseoutFilterFactory::class),
        ]);

    $services->set(IconTemplateLoader::class)
        ->decorate(TemplateLoader::class)
        ->args([
            service(IconTemplateLoader::class . '.inner'),
            service(StorefrontPluginConfigurationFactory::class),
            service(SourceResolver::class),
            param('kernel.project_dir'),
        ]);

    $services->set(TwigAppVariable::class)
        ->decorate('twig.app_variable')
        ->args([
            service(TwigAppVariable::class . '.inner'),
            param('shopware.twig.app_variable.allowed_server_params'),
        ]);

    $services->set(DomainNotMappedListener::class)
        ->args([
            service('service_container'),
        ])
        ->tag('kernel.event_listener', ['event' => 'kernel.exception']);

    $services->set(SalesChannelDomainUtil::class)
        ->args([
            service(RouterInterface::class),
            service(RequestStack::class),
            service(KernelInterface::class),
            service('logger'),
            service(ClockInterface::class),
        ]);

    $services->set(SalesChannelsReadinessCheck::class)
        ->args([
            service(SalesChannelDomainUtil::class),
            service(SalesChannelDomainProvider::class),
        ])
        ->tag('shopware.system_check');

    $services->set(ProductDetailReadinessCheck::class)
        ->args([
            service(SalesChannelDomainUtil::class),
            service(SalesChannelDomainProvider::class),
            service('sales_channel.product.repository'),
            service(SalesChannelContextFactory::class),
        ])
        ->tag('shopware.system_check');

    $services->set(ProductListingReadinessCheck::class)
        ->args([
            service(SalesChannelDomainUtil::class),
            service(Connection::class),
            service(SalesChannelDomainProvider::class),
        ])
        ->tag('shopware.system_check');

    $services->set(SalesChannelDomainProvider::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(RobotsDirectiveParser::class)
        ->args([
            service('event_dispatcher'),
        ]);

    $services->set(RobotsPageLoader::class)
        ->args([
            service('event_dispatcher'),
            service('sales_channel_domain.repository'),
            service(SystemConfigService::class),
            service(RobotsDirectiveParser::class),
        ]);

    $services->set(RobotsConfigChangeSubscriber::class)
        ->args([
            service(RobotsDirectiveParser::class),
            service('logger'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(RobotsRouteScopeWhitelist::class)
        ->tag('shopware.route_scope_whitelist');

    $services->set(TwigComponentRenderEventListener::class)
        ->args([
            param('kernel.environment'),
        ])
        ->tag('kernel.event_listener');

    $services->set(StorybookService::class)
        ->args([
            service('sales_channel.product.repository'),
            service('media.repository'),
            service('sales_channel.repository'),
            service(SalesChannelContextFactory::class),
            service(DatabaseSalesChannelThemeLoader::class),
            service(ThemeRuntimeConfigStorage::class),
        ]);

    $services->set(StorybookRouteScopeAllowList::class)
        ->tag('shopware.route_scope_whitelist');
};
