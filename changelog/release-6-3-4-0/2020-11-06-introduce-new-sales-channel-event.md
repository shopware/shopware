---
title: Introduce a new interface for events containing the SalesChannelContext 
issue: NEXT-11926
author: Michael Telgmann
---
# Core
* Added new interface `Shopware\Core\Framework\Event\ShopwareSalesChannelEvent`. Use it to indicate that your event contains the `Shopware\Core\System\SalesChannel\SalesChannelContext`
* Added `Shopware\Core\Framework\Event\ShopwareSalesChannelEvent` interface to the following event classes
  * `Shopware\Core\Checkout\Cart\Order\CartConvertedEvent`
  * `Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent`
  * `Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent`
  * `Shopware\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent`
  * `Shopware\Core\Checkout\Customer\Event\CustomerDeletedEvent`
  * `Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent`
  * `Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent`
  * `Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent`
  * `Shopware\Core\Checkout\Customer\Event\CustomerWishlistLoaderCriteriaEvent`
  * `Shopware\Core\Checkout\Customer\Event\CustomerWishlistProductListingResultEvent`
  * `Shopware\Core\Content\Category\Event\NavigationLoadedEvent`
  * `Shopware\Core\Content\Cms\Events\CmsPageLoadedEvent`
  * `Shopware\Core\Content\Cms\Events\CmsPageLoaderCriteriaEvent`
  * `Shopware\Core\Content\Product\Events\ProductCrossSellingCriteriaEvent`
  * `Shopware\Core\Content\Product\Events\ProductCrossSellingsLoadedEvent`
  * `Shopware\Core\Content\Product\Events\ProductListingCollectFilterEvent`
  * `Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent`
  * `Shopware\Core\Content\Product\Events\ProductListingResultEvent`
  * `Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent`
  * `Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityAggregationResultLoadedEvent`
  * `Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityIdSearchResultLoadedEvent`
  * `Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent`
  * `Shopware\Core\System\SalesChannel\Entity\SalesChannelEntitySearchResultLoadedEvent`
  * `Shopware\Core\System\SalesChannel\Event\SalesChannelContextPermissionsChangedEvent`
  * `Shopware\Core\System\SalesChannel\Event\SalesChannelContextSwitchEvent`
  * `Shopware\Core\System\SalesChannel\Event\SalesChannelContextTokenChangeEvent`
* Deprecated following classes which will implement the `Shopware\Core\Framework\Event\ShopwareSalesChannelEvent` interface with Shopware 6.4.0.0
  * `Shopware\Core\Checkout\Cart\Event\CartDeletedEvent`
  * `Shopware\Core\Checkout\Cart\Event\CartMergedEvent`
  * `Shopware\Core\Checkout\Cart\Event\CartSavedEvent`
  * `Shopware\Core\Checkout\Cart\Event\LineItemAddedEvent`
  * `Shopware\Core\Checkout\Cart\Event\LineItemQuantityChangedEvent`
  * `Shopware\Core\Checkout\Cart\Event\LineItemRemovedEvent`
* Deprecated following methods which will return `Shopware\Core\Framework\Context` with Shopware 6.4.0.0. Use `getSalesChannelContext()` to get the `Shopware\Core\System\SalesChannel\SalesChannelContext` instead.
  * `Shopware\Core\Checkout\Cart\Event\CartDeletedEvent::getContext()`
  * `Shopware\Core\Checkout\Cart\Event\CartMergedEvent::getContext()`
  * `Shopware\Core\Checkout\Cart\Event\CartSavedEvent::getContext()`
  * `Shopware\Core\Checkout\Cart\Event\LineItemAddedEvent::getContext()`
  * `Shopware\Core\Checkout\Cart\Event\LineItemQuantityChangedEvent::getContext()`
  * `Shopware\Core\Checkout\Cart\Event\LineItemRemovedEvent::getContext()`
___
# Storefront
* Added `Shopware\Core\Framework\Event\ShopwareSalesChannelEvent` interface to the following event classes
  * `Shopware\Storefront\Event\StorefrontRenderEvent`
  * `Shopware\Storefront\Event\RouteRequest\RouteRequestEvent`
  * `Shopware\Storefront\Page\PageLoadedEvent`
  * `Shopware\Storefront\Page\Address\Listing\AddressListingCriteriaEvent`
  * `Shopware\Storefront\Page\Product\ProductLoaderCriteriaEvent`
  * `Shopware\Storefront\Page\Product\CrossSelling\CrossSellingLoadedEvent`
  * `Shopware\Storefront\Page\Product\CrossSelling\CrossSellingProductCriteriaEvent`
  * `Shopware\Storefront\Page\Product\Review\ProductReviewsLoadedEvent`
  * `Shopware\Storefront\Pagelet\PageletLoadedEvent`
* Deprecated following classes which will implement the `Shopware\Core\Framework\Event\ShopwareSalesChannelEvent` interface with Shopware 6.4.0.0
  * `Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPageCriteriaEvent`
  * `Shopware\Storefront\Page\Product\ProductPageCriteriaEvent`
* Deprecated following methods which will return the `Shopware\Core\Framework\Context` with Shopware 6.4.0.0. Use `getSalesChannelContext()` to get the `Shopware\Core\System\SalesChannel\SalesChannelContext` instead.
  * `Shopware\Storefront\Page\Product\QuickView\MinimalQuickViewPageCriteriaEvent::getContext()`
  * `Shopware\Storefront\Page\Product\ProductPageCriteriaEvent::getContext()`
