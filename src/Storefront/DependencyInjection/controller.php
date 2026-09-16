<?php declare(strict_types=1);

namespace Shopware\Storefront\DependencyInjection;

use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\SalesChannel\CartLoadRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\AddWishlistProductRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ChangeCustomerProfileRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ChangeEmailRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ChangePasswordRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ConvertGuestRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\DeleteAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\DeleteCustomerRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\DownloadRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ImitateCustomerRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ListAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LoadWishlistRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LoginRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LogoutRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\MergeWishlistProductRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterConfirmRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\RemoveWishlistProductRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ResetPasswordRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\SendPasswordRecoveryMailRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\UpsertAddressRoute;
use Shopware\Core\Checkout\Document\SalesChannel\DocumentRoute;
use Shopware\Core\Checkout\Order\SalesChannel\CancelOrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderRoute;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Order\SalesChannel\SetPaymentOrderRoute;
use Shopware\Core\Checkout\Payment\PaymentProcessor;
use Shopware\Core\Checkout\Payment\SalesChannel\HandlePaymentMethodRoute;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopware\Core\Content\Category\Service\CategoryUrlGenerator;
use Shopware\Core\Content\Cms\SalesChannel\CmsRoute;
use Shopware\Core\Content\ContactForm\SalesChannel\ContactFormRoute;
use Shopware\Core\Content\Cookie\SalesChannel\CookieRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterConfirmRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterUnsubscribeRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\ProductListRoute;
use Shopware\Core\Content\Product\SalesChannel\PurchaseLimit\ProductPurchaseLimitRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoader;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewSaveRoute;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRoute;
use Shopware\Core\Content\RevocationRequest\SalesChannel\RevocationRequestRoute;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Sitemap\SalesChannel\SitemapFileRoute;
use Shopware\Core\Framework\Adapter\Translation\ConstraintViolationTranslator;
use Shopware\Core\Framework\App\Api\AppJWTGenerateRoute;
use Shopware\Core\Framework\Gateway\Context\SalesChannel\ContextGatewayRoute;
use Shopware\Core\Framework\Script\Api\ScriptResponseEncoder;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopware\Storefront\Controller\AccountOrderController;
use Shopware\Storefront\Controller\AccountProfileController;
use Shopware\Storefront\Controller\AddressController;
use Shopware\Storefront\Controller\Api\CaptchaController as ApiCaptchaController;
use Shopware\Storefront\Controller\AppController;
use Shopware\Storefront\Controller\AuthController;
use Shopware\Storefront\Controller\CaptchaController;
use Shopware\Storefront\Controller\CartLineItemController;
use Shopware\Storefront\Controller\CheckoutController;
use Shopware\Storefront\Controller\CmsController;
use Shopware\Storefront\Controller\ContextController;
use Shopware\Storefront\Controller\ContextGatewayController;
use Shopware\Storefront\Controller\CookieController;
use Shopware\Storefront\Controller\CountryStateController;
use Shopware\Storefront\Controller\DocumentController;
use Shopware\Storefront\Controller\DownloadController;
use Shopware\Storefront\Controller\ErrorController;
use Shopware\Storefront\Controller\FormController;
use Shopware\Storefront\Controller\LandingPageController;
use Shopware\Storefront\Controller\MaintenanceController;
use Shopware\Storefront\Controller\NavigationController;
use Shopware\Storefront\Controller\NewsletterController;
use Shopware\Storefront\Controller\ProductController;
use Shopware\Storefront\Controller\RegisterController;
use Shopware\Storefront\Controller\RobotsController;
use Shopware\Storefront\Controller\ScriptController;
use Shopware\Storefront\Controller\SearchController;
use Shopware\Storefront\Controller\SitemapController;
use Shopware\Storefront\Controller\StorybookController;
use Shopware\Storefront\Controller\VerificationHashController;
use Shopware\Storefront\Controller\WellKnownController;
use Shopware\Storefront\Controller\WishlistController;
use Shopware\Storefront\Framework\Captcha\BasicCaptcha;
use Shopware\Storefront\Framework\Guard\DoubleSubmitGuard;
use Shopware\Storefront\Framework\Routing\MaintenanceModeResolver;
use Shopware\Storefront\Framework\Twig\ErrorTemplateResolver;
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
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\LandingPage\LandingPageLoader;
use Shopware\Storefront\Page\Maintenance\MaintenancePageLoader;
use Shopware\Storefront\Page\Navigation\Error\ErrorPageLoader;
use Shopware\Storefront\Page\Navigation\NavigationPageLoader;
use Shopware\Storefront\Page\Newsletter\Subscribe\NewsletterSubscribePageLoader;
use Shopware\Storefront\Page\Product\ProductPageLoader;
use Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPageLoader;
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
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->public();

    $services->set(ApiCaptchaController::class)
        ->public()
        ->args([
            tagged_iterator('shopware.storefront.captcha'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AccountOrderController::class)
        ->args([
            service(AccountOrderPageLoader::class),
            service(AccountEditOrderPageLoader::class),
            service(ContextSwitchRoute::class),
            service(CancelOrderRoute::class),
            service(SetPaymentOrderRoute::class),
            service(HandlePaymentMethodRoute::class),
            service('event_dispatcher'),
            service(AccountOrderDetailPageLoader::class),
            service(OrderRoute::class),
            service(SalesChannelContextService::class),
            service(SystemConfigService::class),
            service(OrderService::class),
            service(HeaderPageletLoader::class),
            service(FooterPageletLoader::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AccountProfileController::class)
        ->args([
            service(AccountOverviewPageLoader::class),
            service(AccountProfilePageLoader::class),
            service(ChangeCustomerProfileRoute::class),
            service(ChangePasswordRoute::class),
            service(ChangeEmailRoute::class),
            service(DeleteCustomerRoute::class),
            service('logger'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AddressController::class)
        ->args([
            service(AddressListingPageLoader::class),
            service(AddressDetailPageLoader::class),
            service(AccountService::class),
            service(ListAddressRoute::class),
            service(UpsertAddressRoute::class),
            service(DeleteAddressRoute::class),
            service(ContextSwitchRoute::class),
            service(SalesChannelContextService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AuthController::class)
        ->args([
            service(AccountLoginPageLoader::class),
            service(SendPasswordRecoveryMailRoute::class),
            service(ResetPasswordRoute::class),
            service(LoginRoute::class),
            service(LogoutRoute::class),
            service(ImitateCustomerRoute::class),
            service(StorefrontCartFacade::class),
            service(AccountRecoverPasswordPageLoader::class),
            service(ConvertGuestRoute::class),
            service(SystemConfigService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AppController::class)
        ->public()
        ->args([
            service(AppJWTGenerateRoute::class),
        ]);

    $services->set(CartLineItemController::class)
        ->args([
            service(CartService::class),
            service(PromotionItemBuilder::class),
            service(ProductLineItemFactory::class),
            service(HtmlSanitizer::class),
            service(ProductListRoute::class),
            service(LineItemFactoryRegistry::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(CheckoutController::class)
        ->args([
            service(CartService::class),
            service(CheckoutCartPageLoader::class),
            service(CheckoutConfirmPageLoader::class),
            service(CheckoutFinishPageLoader::class),
            service(OrderService::class),
            service(PaymentProcessor::class),
            service(OffcanvasCartPageLoader::class),
            service(LogoutRoute::class),
            service(CartLoadRoute::class),
            service(HeaderPageletLoader::class),
            service(FooterPageletLoader::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(ContextGatewayController::class)
        ->args([
            service(ContextGatewayRoute::class),
            service(CartService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(CookieController::class)
        ->args([
            service(CookieRoute::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(CmsController::class)
        ->args([
            service(CmsRoute::class),
            service(CategoryRoute::class),
            service(ProductListingRoute::class),
            service(ProductDetailRoute::class),
            service(ProductReviewLoader::class),
            service(FindProductVariantRoute::class),
            service('event_dispatcher'),
            service(SystemConfigService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(FormController::class)
        ->args([
            service(ContactFormRoute::class),
            service(NewsletterSubscribeRoute::class),
            service(NewsletterUnsubscribeRoute::class),
            service(RevocationRequestRoute::class),
            service(ConstraintViolationTranslator::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(ContextController::class)
        ->args([
            service(ContextSwitchRoute::class),
            service('request_stack'),
            service('router.default'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(MaintenanceController::class)
        ->public()
        ->args([
            service(SystemConfigService::class),
            service(MaintenancePageLoader::class),
            service(MaintenanceModeResolver::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(ErrorController::class)
        ->public()
        ->args([
            service(ErrorTemplateResolver::class),
            service(SystemConfigService::class),
            service(ErrorPageLoader::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(NavigationController::class)
        ->args([
            service(NavigationPageLoader::class),
            service(MenuOffcanvasPageletLoader::class),
            service(HeaderPageletLoader::class),
            service(FooterPageletLoader::class),
            service(CategoryUrlGenerator::class),
            service(SeoUrlPlaceholderHandlerInterface::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(NewsletterController::class)
        ->args([
            service(NewsletterSubscribePageLoader::class),
            service(NewsletterConfirmRoute::class),
            service(NewsletterAccountPageletLoader::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(ProductController::class)
        ->args([
            service(ProductPageLoader::class),
            service(FindProductVariantRoute::class),
            service(MinimalQuickViewPageLoader::class),
            service(ProductReviewSaveRoute::class),
            service(SeoUrlPlaceholderHandlerInterface::class),
            service(ProductReviewLoader::class),
            service(ProductPurchaseLimitRoute::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(LandingPageController::class)
        ->args([
            service(LandingPageLoader::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(RegisterController::class)
        ->args([
            service(AccountLoginPageLoader::class),
            service(RegisterRoute::class),
            service(RegisterConfirmRoute::class),
            service(CartService::class),
            service(CheckoutRegisterPageLoader::class),
            service(SystemConfigService::class),
            service('customer.repository'),
            service(CustomerGroupRegistrationPageLoader::class),
            service('sales_channel_domain.repository'),
            service(HeaderPageletLoader::class),
            service(FooterPageletLoader::class),
            service(DoubleSubmitGuard::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(ScriptController::class)
        ->args([
            service(GenericPageLoader::class),
            service(ScriptResponseEncoder::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(SearchController::class)
        ->args([
            service(SearchPageLoader::class),
            service(SuggestPageLoader::class),
            service(ProductSearchRoute::class),
            param('shopware.storefront.redirect_on_single_hit_fields'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(SitemapController::class)
        ->args([
            service(SitemapPageLoader::class),
            service(SitemapFileRoute::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(CountryStateController::class)
        ->public()
        ->args([
            service(CountryStateDataPageletLoader::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(DocumentController::class)
        ->public()
        ->args([
            service(DocumentRoute::class),
            service(LogoutRoute::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(DownloadController::class)
        ->public()
        ->args([
            service(DownloadRoute::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(WellKnownController::class)
        ->public()
        ->call('setContainer', [service('service_container')]);

    $services->set(WishlistController::class)
        ->public()
        ->args([
            service(WishlistPageLoader::class),
            service(LoadWishlistRoute::class),
            service(AddWishlistProductRoute::class),
            service(RemoveWishlistProductRoute::class),
            service(MergeWishlistProductRoute::class),
            service(GuestWishlistPageLoader::class),
            service(GuestWishlistPageletLoader::class),
            service('event_dispatcher'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(CaptchaController::class)
        ->public()
        ->args([
            service(BasicCaptchaPageletLoader::class),
            service(BasicCaptcha::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(VerificationHashController::class)
        ->public()
        ->args([
            service(SystemConfigService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(RobotsController::class)
        ->public()
        ->args([
            service(RobotsPageLoader::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(StorybookController::class)
        ->public()
        ->args([
            service('twig'),
            service(StorybookService::class),
        ])
        ->call('setContainer', [service('service_container')]);
};
