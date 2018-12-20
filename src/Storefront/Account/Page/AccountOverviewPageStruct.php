<?php declare(strict_types=1);

namespace Shopware\Storefront\Account\Page;

use Shopware\Storefront\Checkout\Page\CartInfoPageletStruct;
use Shopware\Storefront\Content\Page\CurrencyPageletStruct;
use Shopware\Storefront\Content\Page\HeaderPageletTrait;
use Shopware\Storefront\Content\Page\LanguagePageletStruct;
use Shopware\Storefront\Content\Page\ShopmenuPageletStruct;
use Shopware\Storefront\Framework\Page\PageStruct;
use Shopware\Storefront\Listing\Page\NavigationPageletStruct;

class AccountOverviewPageStruct extends PageStruct
{
    use HeaderPageletTrait;

    /**
     * @var CustomerPageletStruct
     */
    protected $profile;

    public function __construct()
    {
        $this->profile = new CustomerPageletStruct();
        $this->language = new LanguagePageletStruct();
        $this->cartInfo = new CartInfoPageletStruct();
        $this->shopmenu = new ShopmenuPageletStruct();
        $this->currency = new CurrencyPageletStruct();
        $this->navigation = new NavigationPageletStruct();
    }

    /**
     * @return CustomerPageletStruct
     */
    public function getProfile(): CustomerPageletStruct
    {
        return $this->profile;
    }

    /**
     * @param CustomerPageletStruct $customer
     */
    public function setProfile(CustomerPageletStruct $customer): void
    {
        $this->profile = $customer;
    }
}
