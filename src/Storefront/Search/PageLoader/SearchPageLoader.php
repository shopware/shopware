<?php declare(strict_types=1);

namespace Shopware\Storefront\Search\PageLoader;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Checkout\PageLoader\CartInfoPageletLoader;
use Shopware\Storefront\Content\PageLoader\CurrencyPageletLoader;
use Shopware\Storefront\Content\PageLoader\LanguagePageletLoader;
use Shopware\Storefront\Content\PageLoader\ShopmenuPageletLoader;
use Shopware\Storefront\Listing\PageLoader\NavigationPageletLoader;
use Shopware\Storefront\Search\Page\SearchPageRequest;
use Shopware\Storefront\Search\Page\SearchPageStruct;
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
        $page->attach(
            $searchLoader->load($request, $context)
        );

        /** @var NavigationPageletLoader $navigatonLoader */
        $navigatonLoader = $this->container->get(NavigationPageletLoader::class);
        $page->attach(
            $navigatonLoader->load($request, $context)
        );

        /** @var CartInfoPageletLoader $cartInfoLoader */
        $cartInfoLoader = $this->container->get(CartInfoPageletLoader::class);
        $page->attach(
            $cartInfoLoader->load($request, $context)
        );

        /** @var ShopmenuPageletLoader $shopmenuLoader */
        $shopmenuLoader = $this->container->get(ShopmenuPageletLoader::class);
        $page->attach(
            $shopmenuLoader->load($request, $context)
        );

        /** @var LanguagePageletLoader $languageLoader */
        $languageLoader = $this->container->get(LanguagePageletLoader::class);
        $page->attach(
            $languageLoader->load($request, $context)
        );

        /** @var CurrencyPageletLoader $currencyLoader */
        $currencyLoader = $this->container->get(CurrencyPageletLoader::class);
        $page->attach(
            $currencyLoader->load($request, $context)
        );

        return $page;
    }
}
