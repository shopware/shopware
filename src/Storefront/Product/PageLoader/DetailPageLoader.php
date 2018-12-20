<?php declare(strict_types=1);

namespace Shopware\Storefront\Product\PageLoader;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Checkout\PageLoader\CartInfoPageletLoader;
use Shopware\Storefront\Content\PageLoader\CurrencyPageletLoader;
use Shopware\Storefront\Content\PageLoader\LanguagePageletLoader;
use Shopware\Storefront\Content\PageLoader\ShopmenuPageletLoader;
use Shopware\Storefront\Framework\Page\PageRequest;
use Shopware\Storefront\Listing\PageLoader\NavigationPageletLoader;
use Shopware\Storefront\Product\Page\DetailPageStruct;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DetailPageLoader
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct()
    {
    }

    /**
     * @param ContainerInterface|null $container
     */
    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
    }

    /**
     * @param string          $productId
     * @param PageRequest     $request
     * @param CheckoutContext $context
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return DetailPageStruct
     */
    public function load(string $productId, PageRequest $request, CheckoutContext $context): DetailPageStruct
    {
        $page = new DetailPageStruct();

        /** @var DetailPageletLoader $detailLoader */
        $detailLoader = $this->container->get(DetailPageletLoader::class);
        $page->attach(
            $detailLoader->load($productId, $request, $context)
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
