<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Header;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletRequest;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletRequest;
use Shopware\Storefront\Pagelet\Language\LanguagePageletRequest;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletRequest;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletRequest;

class HeaderPageletRequest extends Struct
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
     * @var CurrencyPageletRequest
     */
    protected $currencyRequest;

    /**
     * @var LanguagePageletRequest
     */
    protected $languageRequest;

    public function __construct()
    {
        $this->navigationRequest = new NavigationPageletRequest();
        $this->cartInfoRequest = new CartInfoPageletRequest();
        $this->shopmenuRequest = new ShopmenuPageletRequest();
        $this->currencyRequest = new CurrencyPageletRequest();
        $this->languageRequest = new LanguagePageletRequest();
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
     * @return CurrencyPageletRequest
     */
    public function getCurrencyRequest(): CurrencyPageletRequest
    {
        return $this->currencyRequest;
    }

    /**
     * @param CurrencyPageletRequest $currencyRequest
     */
    public function setCurrencyRequest(CurrencyPageletRequest $currencyRequest): void
    {
        $this->currencyRequest = $currencyRequest;
    }

    /**
     * @return LanguagePageletRequest
     */
    public function getLanguageRequest(): LanguagePageletRequest
    {
        return $this->languageRequest;
    }

    /**
     * @param LanguagePageletRequest $languageRequest
     */
    public function setLanguageRequest(LanguagePageletRequest $languageRequest): void
    {
        $this->languageRequest = $languageRequest;
    }
}
