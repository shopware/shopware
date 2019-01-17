<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentHeader;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
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

    public function load(InternalRequest $request, CheckoutContext $context): ContentHeaderPageletStruct
    {
        $headerPageletStruct = new ContentHeaderPageletStruct();
        $headerPageletStruct->setNavigation($this->navigationLoader->load($request, $context));
        $headerPageletStruct->setCartInfo($this->cartInfoLoader->load($request, $context));
        $headerPageletStruct->setShopmenu($this->shopmenuLoader->load($request, $context));
        $headerPageletStruct->setCurrency($this->currencyLoader->load($request, $context));
        $headerPageletStruct->setLanguage($this->languageLoader->load($request, $context));

        return $headerPageletStruct;
    }
}
