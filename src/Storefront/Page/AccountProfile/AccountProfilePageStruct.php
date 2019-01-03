<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountProfile;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\AccountProfile\AccountProfilePageletStruct;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletStruct;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletStruct;
use Shopware\Storefront\Pagelet\Header\HeaderPageletStructTrait;
use Shopware\Storefront\Pagelet\Language\LanguagePageletStruct;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletStruct;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletStruct;

class AccountProfilePageStruct extends Struct
{
    use HeaderPageletStructTrait;

    /**
     * @var AccountProfilePageletStruct
     */
    protected $profile;

    public function __construct()
    {
        $this->profile = new AccountProfilePageletStruct();
        $this->language = new LanguagePageletStruct();
        $this->cartInfo = new CartInfoPageletStruct();
        $this->shopmenu = new ShopmenuPageletStruct();
        $this->currency = new CurrencyPageletStruct();
        $this->navigation = new NavigationPageletStruct();
    }

    /**
     * @return AccountProfilePageletStruct
     */
    public function getProfile(): AccountProfilePageletStruct
    {
        return $this->profile;
    }

    /**
     * @param AccountProfilePageletStruct $customer
     */
    public function setProfile(AccountProfilePageletStruct $customer): void
    {
        $this->profile = $customer;
    }
}
