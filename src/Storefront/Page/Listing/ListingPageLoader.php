<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Listing;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletLoader;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletLoader;
use Shopware\Storefront\Pagelet\Language\LanguagePageletLoader;
use Shopware\Storefront\Pagelet\Listing\ListingPageletLoader;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletLoader;
use Shopware\Storefront\Pagelet\NavigationSidebar\NavigationSidebarPageletLoader;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ListingPageLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param ContainerInterface|null $container
     */
    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
    }

    /**
     * @param ListingPageRequest $request
     * @param CheckoutContext    $context
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return ListingPageStruct
     */
    public function load(ListingPageRequest $request, CheckoutContext $context): ListingPageStruct
    {
        $page = new ListingPageStruct();

        /** @var ListingPageletLoader $listingLoader */
        $listingLoader = $this->container->get(ListingPageletLoader::class);
        $page->setListing(
            $listingLoader->load($request->getListingRequest(), $context)
        );

        /** @var NavigationSidebarPageletLoader $navigationSidebarLoader */
        $navigationSidebarLoader = $this->container->get(NavigationSidebarPageletLoader::class);
        $page->setNavigationSidebar(
            $navigationSidebarLoader->load($request->getNavigationSidebarRequest(), $context)
        );

        /** @var NavigationPageletLoader $navigationLoader */
        $navigationLoader = $this->container->get(NavigationPageletLoader::class);
        $page->setNavigation(
            $navigationLoader->load($request->getNavigationRequest(), $context)
        );

        /** @var CartInfoPageletLoader $cartinfoLoader */
        $cartinfoLoader = $this->container->get(CartInfoPageletLoader::class);
        $page->setCartInfo(
            $cartinfoLoader->load($request->getCartInfoRequest(), $context)
        );

        /** @var ShopmenuPageletLoader $shopmenuLoader */
        $shopmenuLoader = $this->container->get(ShopmenuPageletLoader::class);
        $page->setShopmenu(
            $shopmenuLoader->load($request->getShopmenuRequest(), $context)
        );

        /** @var LanguagePageletLoader $languageLoader */
        $languageLoader = $this->container->get(LanguagePageletLoader::class);
        $page->setLanguage(
            $languageLoader->load($request->getLanguageRequest(), $context)
        );

        /** @var CurrencyPageletLoader $currencyLoader */
        $currencyLoader = $this->container->get(CurrencyPageletLoader::class);
        $page->setCurrency(
            $currencyLoader->load($request->getCurrencyRequest(), $context)
        );

        return $page;
    }
}
