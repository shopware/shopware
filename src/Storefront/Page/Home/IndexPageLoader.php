<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Home;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletLoader;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletLoader;
use Shopware\Storefront\Pagelet\Language\LanguagePageletLoader;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletLoader;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IndexPageLoader
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @param ContainerInterface|null $container
     */
    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
    }

    /**
     * @param IndexPageRequest $request
     * @param CheckoutContext  $context
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return IndexPageStruct
     */
    public function load(IndexPageRequest $request, CheckoutContext $context): IndexPageStruct
    {
        $page = new IndexPageStruct();

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
