<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentHeader;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletLoader;
use Shopware\Storefront\Pagelet\ContentCurrency\ContentCurrencyPageletLoader;
use Shopware\Storefront\Pagelet\ContentLanguage\ContentLanguagePageletLoader;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletLoader;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletLoader;

class ContentHeaderPageletLoader
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
     * @var ContentCurrencyPageletLoader
     */
    private $currencyLoader;

    /**
     * @var ContentLanguagePageletLoader
     */
    private $languageLoader;

    public function __construct(
        NavigationPageletLoader $navigationLoader,
        CartInfoPageletLoader $cartInfoLoader,
        ShopmenuPageletLoader $shopmenuLoader,
        ContentCurrencyPageletLoader $currencyLoader,
        ContentLanguagePageletLoader $languageLoader
    ) {
        $this->navigationLoader = $navigationLoader;
        $this->cartInfoLoader = $cartInfoLoader;
        $this->shopmenuLoader = $shopmenuLoader;
        $this->currencyLoader = $currencyLoader;
        $this->languageLoader = $languageLoader;
    }

    /**
     * @param ContentHeaderPageletRequest $request
     * @param CheckoutContext             $context
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException
     *
     * @return ContentHeaderPageletStruct
     */
    public function load(ContentHeaderPageletRequest $request, CheckoutContext $context): ContentHeaderPageletStruct
    {
        $headerPageletStruct = new ContentHeaderPageletStruct();
        $headerPageletStruct->setNavigation($this->navigationLoader->load($request->getNavigationRequest(), $context));
        $headerPageletStruct->setCartInfo($this->cartInfoLoader->load($request->getCartInfoRequest(), $context));
        $headerPageletStruct->setShopmenu($this->shopmenuLoader->load($request->getShopmenuRequest(), $context));
        $headerPageletStruct->setCurrency($this->currencyLoader->load($request->getCurrencyRequest(), $context));
        $headerPageletStruct->setLanguage($this->languageLoader->load($request->getLanguageRequest(), $context));

        return $headerPageletStruct;
    }
}
