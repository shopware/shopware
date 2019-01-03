<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountAddress;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletStruct;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletStruct;
use Shopware\Storefront\Pagelet\Header\HeaderPageletStructTrait;
use Shopware\Storefront\Pagelet\Language\LanguagePageletStruct;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletStruct;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletStruct;

class AccountAddressPageStruct extends Struct
{
    use HeaderPageletStructTrait;

    /**
     * @var AccountAddressPageletStruct
     */
    protected $customerAdressPage;

    public function __construct()
    {
        $this->customerAdressPage = new AccountAddressPageletStruct();
        $this->language = new LanguagePageletStruct();
        $this->cartInfo = new CartInfoPageletStruct();
        $this->shopmenu = new ShopmenuPageletStruct();
        $this->currency = new CurrencyPageletStruct();
        $this->navigation = new NavigationPageletStruct();
    }

    /**
     * @return AccountAddressPageletStruct
     */
    public function getCustomerAdressPage(): AccountAddressPageletStruct
    {
        return $this->customerAdressPage;
    }

    /**
     * @param AccountAddressPageletStruct $customerAdressPage
     */
    public function setCustomerAdressPage(AccountAddressPageletStruct $customerAdressPage): void
    {
        $this->customerAdressPage = $customerAdressPage;
    }
}
