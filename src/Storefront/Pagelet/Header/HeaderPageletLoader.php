<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Header;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletLoader;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletLoader;
use Shopware\Storefront\Pagelet\Language\LanguagePageletLoader;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletLoader;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletLoader;

class HeaderPageletLoader
{
    /**
     * @var NavigationPageletLoader
     */
    private $navigationLoader;

    /**
     * @var CartInfoPageletLoader
     */
    private $cartInfoLoader;

    /**
     * @var ShopmenuPageletLoader
     */
    private $shopmenuLoader;

    /**
     * @var CurrencyPageletLoader
     */
    private $currencyLoader;

    /**
     * @var LanguagePageletLoader
     */
    private $languageLoader;

    public function __construct(
        NavigationPageletLoader $navigationLoader,
        CartInfoPageletLoader $cartInfoLoader,
        ShopmenuPageletLoader $shopmenuLoader,
        CurrencyPageletLoader $currencyLoader,
        LanguagePageletLoader $languageLoader
    ) {
        $this->navigationLoader = $navigationLoader;
        $this->cartInfoLoader = $cartInfoLoader;
        $this->shopmenuLoader = $shopmenuLoader;
        $this->currencyLoader = $currencyLoader;
        $this->languageLoader = $languageLoader;
    }

    public function load(HeaderPageletRequest $request, CheckoutContext $context): HeaderPageletStruct
    {
        return new HeaderPageletStruct(
            $this->navigationLoader->load($request->getNavigationRequest(), $context),
            $this->cartInfoLoader->load($request->getCartInfoRequest(), $context),
            $this->shopmenuLoader->load($request->getShopmenuRequest(), $context),
            $this->currencyLoader->load($request->getCurrencyRequest(), $context),
            $this->languageLoader->load($request->getLanguageRequest(), $context)
        );
    }
}
