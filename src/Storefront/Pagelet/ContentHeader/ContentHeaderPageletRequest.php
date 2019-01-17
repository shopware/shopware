<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentHeader;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletRequest;
use Shopware\Storefront\Pagelet\ContentCurrency\ContentCurrencyPageletRequest;
use Shopware\Storefront\Pagelet\ContentLanguage\ContentLanguagePageletRequest;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletRequest;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletRequest;

class ContentHeaderPageletRequest extends Struct
{
    /**
     * @var NavigationPageletRequest
     */
    protected $navigationRequest;

    /**
     * @var CartInfoPageletRequest
     */
    protected $cartInfoRequest;

    /**
     * @var ShopmenuPageletRequest
     */
    protected $shopmenuRequest;

    /**
     * @var ContentCurrencyPageletRequest
     */
    protected $currencyRequest;

    /**
     * @var ContentLanguagePageletRequest
     */
    protected $languageRequest;

    public function __construct()
    {
        $this->navigationRequest = new NavigationPageletRequest();
        $this->cartInfoRequest = new CartInfoPageletRequest();
        $this->shopmenuRequest = new ShopmenuPageletRequest();
        $this->currencyRequest = new ContentCurrencyPageletRequest();
        $this->languageRequest = new ContentLanguagePageletRequest();
    }

    /**
     * @return NavigationPageletRequest
     */
    public function getNavigationRequest(): NavigationPageletRequest
    {
        return $this->navigationRequest;
    }

    /**
     * @param NavigationPageletRequest $navigationRequest
     */
    public function setNavigationRequest(NavigationPageletRequest $navigationRequest): void
    {
        $this->navigationRequest = $navigationRequest;
    }

    /**
     * @return CartInfoPageletRequest
     */
    public function getCartInfoRequest(): CartInfoPageletRequest
    {
        return $this->cartInfoRequest;
    }

    /**
     * @param CartInfoPageletRequest $cartInfoRequest
     */
    public function setCartInfoRequest(CartInfoPageletRequest $cartInfoRequest): void
    {
        $this->cartInfoRequest = $cartInfoRequest;
    }

    /**
     * @return ShopmenuPageletRequest
     */
    public function getShopmenuRequest(): ShopmenuPageletRequest
    {
        return $this->shopmenuRequest;
    }

    /**
     * @param ShopmenuPageletRequest $shopmenuRequest
     */
    public function setShopmenuRequest(ShopmenuPageletRequest $shopmenuRequest): void
    {
        $this->shopmenuRequest = $shopmenuRequest;
    }

    /**
     * @return ContentCurrencyPageletRequest
     */
    public function getCurrencyRequest(): ContentCurrencyPageletRequest
    {
        return $this->currencyRequest;
    }

    /**
     * @param ContentCurrencyPageletRequest $currencyRequest
     */
    public function setCurrencyRequest(ContentCurrencyPageletRequest $currencyRequest): void
    {
        $this->currencyRequest = $currencyRequest;
    }

    /**
     * @return ContentLanguagePageletRequest
     */
    public function getLanguageRequest(): ContentLanguagePageletRequest
    {
        return $this->languageRequest;
    }

    /**
     * @param ContentLanguagePageletRequest $languageRequest
     */
    public function setLanguageRequest(ContentLanguagePageletRequest $languageRequest): void
    {
        $this->languageRequest = $languageRequest;
    }
}
