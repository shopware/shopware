<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Core\PlatformRequest;
use Shopware\Storefront\Framework\Page\PageRequestResolver;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletRequest;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletRequestEvent;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletRequest;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletRequestEvent;
use Shopware\Storefront\Pagelet\Language\LanguagePageletRequest;
use Shopware\Storefront\Pagelet\Language\LanguagePageletRequestEvent;
use Shopware\Storefront\Pagelet\Listing\ListingPageletRequest;
use Shopware\Storefront\Pagelet\Listing\ListingPageletRequestEvent;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletRequest;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletRequestEvent;
use Shopware\Storefront\Pagelet\NavigationSidebar\NavigationSidebarPageletRequest;
use Shopware\Storefront\Pagelet\NavigationSidebar\NavigationSidebarPageletRequestEvent;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletRequest;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletRequestEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

class ListingPageRequestResolver extends PageRequestResolver
{
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->getType() === ListingPageRequest::class;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): \Generator
    {
        $context = $this->requestStack
            ->getMasterRequest()
            ->attributes
            ->get(PlatformRequest::ATTRIBUTE_STOREFRONT_CONTEXT_OBJECT);

        $listingPageRequest = new ListingPageRequest();

        $navigationPageletRequest = new NavigationPageletRequest();
        $event = new NavigationPageletRequestEvent($request, $context, $navigationPageletRequest);
        $this->eventDispatcher->dispatch(NavigationPageletRequestEvent::NAME, $event);
        $listingPageRequest->setNavigationRequest($navigationPageletRequest);

        $currencyPageletRequest = new CurrencyPageletRequest();
        $event = new CurrencyPageletRequestEvent($request, $context, $currencyPageletRequest);
        $this->eventDispatcher->dispatch(CurrencyPageletRequestEvent::NAME, $event);
        $listingPageRequest->setCurrencyRequest($currencyPageletRequest);

        $cartInfoPageletRequest = new CartInfoPageletRequest();
        $event = new CartInfoPageletRequestEvent($request, $context, $cartInfoPageletRequest);
        $this->eventDispatcher->dispatch(CartInfoPageletRequestEvent::NAME, $event);
        $listingPageRequest->setCartInfoRequest($cartInfoPageletRequest);

        $languagePageletRequest = new LanguagePageletRequest();
        $event = new LanguagePageletRequestEvent($request, $context, $languagePageletRequest);
        $this->eventDispatcher->dispatch(LanguagePageletRequestEvent::NAME, $event);
        $listingPageRequest->setLanguageRequest($languagePageletRequest);

        $shopmenuPageletRequest = new ShopmenuPageletRequest();
        $event = new ShopmenuPageletRequestEvent($request, $context, $shopmenuPageletRequest);
        $this->eventDispatcher->dispatch(ShopmenuPageletRequestEvent::NAME, $event);
        $listingPageRequest->setShopmenuRequest($shopmenuPageletRequest);

        $navigationSidebarPageletRequest = new NavigationSidebarPageletRequest();
        $event = new NavigationSidebarPageletRequestEvent($request, $context, $navigationSidebarPageletRequest);
        $this->eventDispatcher->dispatch(NavigationSidebarPageletRequestEvent::NAME, $event);
        $listingPageRequest->setNavigationSidebarRequest($navigationSidebarPageletRequest);

        $listingPageletRequest = new ListingPageletRequest();
        $event = new ListingPageletRequestEvent($request, $context, $listingPageletRequest);
        $this->eventDispatcher->dispatch(ListingPageletRequestEvent::NAME, $event);
        $listingPageRequest->setListingRequest($listingPageletRequest);

        $event = new ListingPageRequestEvent($request, $context, $listingPageRequest);
        $this->eventDispatcher->dispatch(ListingPageRequestEvent::NAME, $event);

        yield $event->getListingPageRequest();
    }
}
