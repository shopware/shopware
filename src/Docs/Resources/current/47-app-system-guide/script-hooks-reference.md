# List of all available Hooks for Scripting

## Data Loading

All available Hooks that can be used to load additional data.

#### customer-group-registration-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | customer-group-registration-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Account\CustomerGroupRegistration\CustomerGroupRegistrationPageLoadedHook`      |
| **Description**        | Triggered when the CustomerGroupRegistrationPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Account\CustomerGroupRegistration\CustomerGroupRegistrationPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### account-guest-login-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | account-guest-login-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Account\Login\AccountGuestLoginPageLoadedHook`      |
| **Description**        | Triggered when the AccountGuestLoginPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Account\Login\AccountLoginPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### account-login-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | account-login-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Account\Login\AccountLoginPageLoadedHook`      |
| **Description**        | Triggered when the AccountLoginPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Account\Login\AccountLoginPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### account-edit-order-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | account-edit-order-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedHook`      |
| **Description**        | Triggered when the AccountEditOrderPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Account\Order\AccountEditOrderPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### account-order-detail-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | account-order-detail-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Account\Order\AccountOrderDetailPageLoadedHook`      |
| **Description**        | Triggered when the AccountOrderDetailPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Account\Order\AccountOrderDetailPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### account-order-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | account-order-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Account\Order\AccountOrderPageLoadedHook`      |
| **Description**        | Triggered when the AccountOrderPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Account\Order\AccountOrderPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### account-overview-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | account-overview-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Account\Overview\AccountOverviewPageLoadedHook`      |
| **Description**        | Triggered when the AccountOverviewPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Account\Overview\AccountOverviewPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### account-payment-method-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | account-payment-method-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPageLoadedHook`      |
| **Description**        | Triggered when the AccountPaymentMethodPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Account\PaymentMethod\AccountPaymentMethodPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### account-profile-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | account-profile-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoadedHook`      |
| **Description**        | Triggered when the AccountProfilePage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Account\Profile\AccountProfilePage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### account-register-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | account-register-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Account\Register\AccountRegisterPageLoadedHook`      |
| **Description**        | Triggered when the AccountLoginPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Account\Login\AccountLoginPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### address-detail-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | address-detail-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Address\Detail\AddressDetailPageLoadedHook`      |
| **Description**        | Triggered when the AddressDetailPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Address\Detail\AddressDetailPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### address-book-widget-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | address-book-widget-loaded         |
| **Class**              | `Shopware\Storefront\Page\Address\Listing\AddressBookWidgetLoadedHook`      |
| **Description**        | Triggered when the AddressBookWidget is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Address\Listing\AddressListingPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### address-listing-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | address-listing-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Address\Listing\AddressListingPageLoadedHook`      |
| **Description**        | Triggered when the AddressListingPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Address\Listing\AddressListingPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### checkout-cart-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | checkout-cart-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedHook`      |
| **Description**        | Triggered when the CheckoutCartPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### checkout-confirm-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | checkout-confirm-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedHook`      |
| **Description**        | Triggered when the CheckoutConfirmPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### checkout-finish-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | checkout-finish-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedHook`      |
| **Description**        | Triggered when the CheckoutFinishPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### checkout-info-widget-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | checkout-info-widget-loaded         |
| **Class**              | `Shopware\Storefront\Page\Checkout\Offcanvas\CheckoutInfoWidgetLoadedHook`      |
| **Description**        | Triggered when the CheckoutInfoWidget is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### checkout-offcanvas-widget-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | checkout-offcanvas-widget-loaded         |
| **Class**              | `Shopware\Storefront\Page\Checkout\Offcanvas\CheckoutOffcanvasWidgetLoadedHook`      |
| **Description**        | Triggered when the CheckoutOffcanvasWidget is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### checkout-register-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | checkout-register-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoadedHook`      |
| **Description**        | Triggered when the CheckoutRegisterPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### cms-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | cms-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Cms\CmsPageLoadedHook`      |
| **Description**        | Triggered when a CmsPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Core\Content\Cms\CmsPageEntity`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### landing-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | landing-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\LandingPage\LandingPageLoadedHook`      |
| **Description**        | Triggered when the LandingPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\LandingPage\LandingPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### maintenance-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | maintenance-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Maintenance\MaintenancePageLoadedHook`      |
| **Description**        | Triggered when the MaintenancePage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Maintenance\MaintenancePage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### navigation-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | navigation-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Navigation\NavigationPageLoadedHook`      |
| **Description**        | Triggered when the NavigationPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Navigation\NavigationPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### product-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | product-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Product\ProductPageLoadedHook`      |
| **Description**        | Triggered when the ProductPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Product\ProductPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### product-quick-view-widget-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | product-quick-view-widget-loaded         |
| **Class**              | `Shopware\Storefront\Page\Product\QuickView\ProductQuickViewWidgetLoadedHook`      |
| **Description**        | Triggered when the ProductQuickViewWidget is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### product-reviews-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | product-reviews-loaded         |
| **Class**              | `Shopware\Storefront\Page\Product\Review\ProductReviewsWidgetLoadedHook`      |
| **Description**        | Triggered when the ProductReviewsWidget is loaded<br>  |
| **Available Data**     | reviews: `Shopware\Storefront\Page\Product\Review\ReviewLoaderResult`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### search-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | search-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Search\SearchPageLoadedHook`      |
| **Description**        | Triggered when the SearchPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Search\SearchPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### search-widget-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | search-widget-loaded         |
| **Class**              | `Shopware\Storefront\Page\Search\SearchWidgetLoadedHook`      |
| **Description**        | Triggered when the SearchWidget is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Search\SearchPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### sitemap-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | sitemap-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Sitemap\SitemapPageLoadedHook`      |
| **Description**        | Triggered when the SitemapPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Sitemap\SitemapPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### suggest-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | suggest-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Suggest\SuggestPageLoadedHook`      |
| **Description**        | Triggered when the SuggestPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Suggest\SuggestPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### guest-wishlist-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | guest-wishlist-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Wishlist\GuestWishlistPageLoadedHook`      |
| **Description**        | Triggered when the GuestWishlistPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Wishlist\GuestWishlistPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### wishlist-page-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | wishlist-page-loaded         |
| **Class**              | `Shopware\Storefront\Page\Wishlist\WishlistPageLoadedHook`      |
| **Description**        | Triggered when the WishlistPage is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Wishlist\WishlistPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### wishlist-widget-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | wishlist-widget-loaded         |
| **Class**              | `Shopware\Storefront\Page\Wishlist\WishlistWidgetLoadedHook`      |
| **Description**        | Triggered when the WishlistWidget is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Page\Wishlist\WishlistPage`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### menu-offcanvas-pagelet-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | menu-offcanvas-pagelet-loaded         |
| **Class**              | `Shopware\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoadedHook`      |
| **Description**        | Triggered when the MenuOffcanvasPagelet is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPagelet`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |

#### guest-wishlist-pagelet-loaded

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | guest-wishlist-pagelet-loaded         |
| **Class**              | `Shopware\Storefront\Pagelet\Wishlist\GuestWishlistPageletLoadedHook`      |
| **Description**        | Triggered when the GuestWishlistPagelet is loaded<br>  |
| **Available Data**     | page: `Shopware\Storefront\Pagelet\Wishlist\GuestWishlistPagelet`<br>context: `Shopware\Core\Framework\Context`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>     |
| **Available Services** | repository: `Shopware\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacade`<br>config: `Shopware\Core\System\SystemConfig\Facade\SystemConfigFacade`<br>store: `Shopware\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacade`<br> |


## Cart Manipulation

All available Hooks that can be used to manipulate the cart.

#### cart

| <!-- -->               | <!-- -->                |
|:-----------------------|:------------------------|
| **Name**               | cart         |
| **Class**              | `Shopware\Core\Checkout\Cart\Hook\CartHook`      |
| **Description**        | Triggered during the cart calculation process.<br>  |
| **Available Data**     | cart: `Shopware\Core\Checkout\Cart\Cart`<br>salesChannelContext: `Shopware\Core\System\SalesChannel\SalesChannelContext`<br>context: `Shopware\Core\Framework\Context`<br>     |
| **Available Services** | cart: `Shopware\Core\Checkout\Cart\Facade\CartFacade`<br> |


