<?php declare(strict_types=1);

namespace Shopware\Storefront\Content\PageLoader;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Checkout\PageLoader\CartInfoPageletLoader;
use Shopware\Storefront\Content\Page\IndexPageStruct;
use Shopware\Storefront\Framework\Page\PageRequest;
use Shopware\Storefront\Framework\PageLoader\PageLoader;
use Shopware\Storefront\Listing\PageLoader\NavigationPageletLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;

class IndexPageLoader implements PageLoader
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
     * @param PageRequest     $request
     * @param CheckoutContext $context
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return IndexPageStruct
     */
    public function load(PageRequest $request, CheckoutContext $context): IndexPageStruct
    {
        $page = new IndexPageStruct();

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
