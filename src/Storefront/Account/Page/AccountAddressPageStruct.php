<?php declare(strict_types=1);

namespace Shopware\Storefront\Account\Page;

use Shopware\Storefront\Checkout\Page\CartInfoPageletStruct;
use Shopware\Storefront\Content\Page\CurrencyPageletStruct;
use Shopware\Storefront\Content\Page\HeaderPageletTrait;
use Shopware\Storefront\Content\Page\LanguagePageletStruct;
use Shopware\Storefront\Content\Page\ShopmenuPageletStruct;
use Shopware\Storefront\Framework\Page\PageStruct;
use Shopware\Storefront\Listing\Page\NavigationPageletStruct;

class AccountAddressPageStruct extends PageStruct
{
    use HeaderPageletTrait;

    /**
     * @var CustomerAddressPageletStruct
     */
    protected $customerAdressPage;

    public function __construct()
    {
        $this->customerAdressPage = new CustomerAddressPageletStruct();
        $this->language = new LanguagePageletStruct();
        $this->cartInfo = new CartInfoPageletStruct();
        $this->shopmenu = new ShopmenuPageletStruct();
        $this->currency = new CurrencyPageletStruct();
        $this->navigation = new NavigationPageletStruct();
    }

    /**
     * @return CustomerAddressPageletStruct
     */
    public function getCustomerAdressPage(): CustomerAddressPageletStruct
    {
        return $this->customerAdressPage;
    }

    /**
     * @param CustomerAddressPageletStruct $customerAdressPage
     */
    public function setCustomerAdressPage(CustomerAddressPageletStruct $customerAdressPage): void
    {
        $this->customerAdressPage = $customerAdressPage;
    }
}
