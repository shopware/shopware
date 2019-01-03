<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletLoader;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletLoader;
use Shopware\Storefront\Pagelet\Language\LanguagePageletLoader;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletLoader;
use Shopware\Storefront\Pagelet\Search\SearchPageletLoader;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SearchPageLoader
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
     * @param SearchPageRequest $request
     * @param CheckoutContext   $context
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return SearchPageStruct
     */
    public function load(SearchPageRequest $request, CheckoutContext $context): SearchPageStruct
    {
        $page = new SearchPageStruct();

        /** @var SearchPageletLoader $searchLoader */
        $searchLoader = $this->container->get(SearchPageletLoader::class);
        $page->setListing(
            $searchLoader->load($request->getSearchRequest(), $context)
        );

        /** @var NavigationPageletLoader $navigationLoader */
        $navigationLoader = $this->container->get(NavigationPageletLoader::class);
        $page->setNavigation(
            $navigationLoader->load($request->getNavigationRequest(), $context)
        );

        /** @var CartInfoPageletLoader $cartInfoLoader */
        $cartInfoLoader = $this->container->get(CartInfoPageletLoader::class);
        $page->setCartInfo(
            $cartInfoLoader->load($request->getCartInfoRequest(), $context)
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
