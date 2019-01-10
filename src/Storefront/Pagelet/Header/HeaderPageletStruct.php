<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Header;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Framework\Page\PageletStruct;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletStruct;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletStruct;
use Shopware\Storefront\Pagelet\Language\LanguagePageletStruct;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletStruct;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletStruct;

class HeaderPageletStruct extends Struct
{
    /**
     * @var NavigationPageletStruct
     */
    protected $navigation;

    /**
     * @var CartInfoPageletStruct
     */
    protected $cartInfo;

    /**
     * @var ShopmenuPageletStruct
     */
    protected $shopmenu;

    /**
     * @var CurrencyPageletStruct
     */
    protected $currency;

    /**
     * @var LanguagePageletStruct
     */
    protected $language;

    public function __construct(
        NavigationPageletStruct $navigation,
        CartInfoPageletStruct $cartInfo,
        ShopmenuPageletStruct $shopmenu,
        CurrencyPageletStruct $currency,
        LanguagePageletStruct $language
    ) {
        $this->navigation = $navigation;
        $this->cartInfo = $cartInfo;
        $this->shopmenu = $shopmenu;
        $this->currency = $currency;
        $this->language = $language;
    }

    /**
     * @return LanguagePageletStruct
     */
    public function getLanguage(): LanguagePageletStruct
    {
        return $this->language;
    }

    /**
     * @param LanguagePageletStruct $language
     */
    public function setLanguage(PageletStruct $language): void
    {
        $this->language = $language;
    }

    /**
     * @return CurrencyPageletStruct
     */
    public function getCurrency(): CurrencyPageletStruct
    {
        return $this->currency;
    }

    /**
     * @param CurrencyPageletStruct $currency
     */
    public function setCurrency(CurrencyPageletStruct $currency): void
    {
        $this->currency = $currency;
    }

    /**
     * @return ShopmenuPageletStruct
     */
    public function getShopmenu(): ShopmenuPageletStruct
    {
        return $this->shopmenu;
    }

    /**
     * @param ShopmenuPageletStruct $shopmenu
     */
    public function setShopmenu(ShopmenuPageletStruct $shopmenu): void
    {
        $this->shopmenu = $shopmenu;
    }

    /**
     * @return NavigationPageletStruct
     */
    public function getNavigation(): NavigationPageletStruct
    {
        return $this->navigation;
    }

    /**
     * @param NavigationPageletStruct $navigation
     */
    public function setNavigation(NavigationPageletStruct $navigation): void
    {
        $this->navigation = $navigation;
    }

    /**
     * @return CartInfoPageletStruct
     */
    public function getCartInfo(): CartInfoPageletStruct
    {
        return $this->cartInfo;
    }

    /**
     * @param CartInfoPageletStruct $cartInfo
     */
    public function setCartInfo(CartInfoPageletStruct $cartInfo): void
    {
        $this->cartInfo = $cartInfo;
    }
}
