<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\ContentHeader;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletStruct;
use Shopware\Storefront\Pagelet\ContentCurrency\ContentCurrencyPageletStruct;
use Shopware\Storefront\Pagelet\ContentLanguage\ContentLanguagePageletStruct;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletStruct;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletStruct;

class ContentHeaderPageletStruct extends Struct
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
     * @var ContentCurrencyPageletStruct
     */
    protected $currency;

    /**
     * @var ContentLanguagePageletStruct
     */
    protected $language;

    /**
     * @return ContentLanguagePageletStruct
     */
    public function getLanguage(): ContentLanguagePageletStruct
    {
        return $this->language;
    }

    /**
     * @param ContentLanguagePageletStruct $language
     */
    public function setLanguage(Struct $language): void
    {
        $this->language = $language;
    }

    /**
     * @return ContentCurrencyPageletStruct
     */
    public function getCurrency(): ContentCurrencyPageletStruct
    {
        return $this->currency;
    }

    /**
     * @param ContentCurrencyPageletStruct $currency
     */
    public function setCurrency(ContentCurrencyPageletStruct $currency): void
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
