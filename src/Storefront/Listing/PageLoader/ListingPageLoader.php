<?php declare(strict_types=1);

namespace Shopware\Storefront\Listing\PageLoader;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Checkout\PageLoader\CartInfoPageletLoader;
use Shopware\Storefront\Content\PageLoader\CurrencyPageletLoader;
use Shopware\Storefront\Content\PageLoader\LanguagePageletLoader;
use Shopware\Storefront\Content\PageLoader\ShopmenuPageletLoader;
use Shopware\Storefront\Framework\PageLoader\PageLoader;
use Shopware\Storefront\Listing\Page\ListingPageRequest;
use Shopware\Storefront\Listing\Page\ListingPageStruct;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ListingPageLoader implements PageLoader
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
        $page->attach(
            $listingLoader->load($request, $context)
        );

        /** @var NavigationPageletLoader $navigatonLoader */
        $navigatonLoader = $this->container->get(NavigationPageletLoader::class);
        $page->attach(
            $navigatonLoader->load($request, $context)
        );

        /** @var CartInfoPageletLoader $cartinfoLoader */
        $cartinfoLoader = $this->container->get(CartInfoPageletLoader::class);
        $page->attach(
            $cartinfoLoader->load($request, $context)
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
