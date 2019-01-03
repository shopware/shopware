<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\ProductDetail;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletLoader;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletLoader;
use Shopware\Storefront\Pagelet\Language\LanguagePageletLoader;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletLoader;
use Shopware\Storefront\Pagelet\ProductDetail\ProductDetailPageletLoader;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ProductDetailPageLoader
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
     * @param string                   $productId
     * @param ProductDetailPageRequest $request
     * @param CheckoutContext          $context
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return ProductDetailPageStruct
     */
    public function load(string $productId, ProductDetailPageRequest $request, CheckoutContext $context): ProductDetailPageStruct
    {
        $page = new ProductDetailPageStruct();

        /** @var ProductDetailPageletLoader $detailLoader */
        $detailLoader = $this->container->get(ProductDetailPageletLoader::class);
        $page->setProductDetail(
            $detailLoader->load($productId, $request->getDetailRequest(), $context)
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
