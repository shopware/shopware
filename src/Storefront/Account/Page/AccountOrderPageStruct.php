<?php declare(strict_types=1);

namespace Shopware\Storefront\Account\Page;

use Shopware\Storefront\Checkout\Page\CartInfoPageletStruct;
use Shopware\Storefront\Content\Page\CurrencyPageletStruct;
use Shopware\Storefront\Content\Page\HeaderPageletTrait;
use Shopware\Storefront\Content\Page\LanguagePageletStruct;
use Shopware\Storefront\Content\Page\ShopmenuPageletStruct;
use Shopware\Storefront\Framework\Page\PageStruct;
use Shopware\Storefront\Listing\Page\NavigationPageletStruct;

class AccountOrderPageStruct extends PageStruct
{
    use HeaderPageletTrait;

    /**
     * @var CustomerOrderPageletStruct
     */
    protected $customerOrders;

    public function __construct()
    {
        $this->customerOrders = new CustomerOrderPageletStruct();
        $this->language = new LanguagePageletStruct();
        $this->cartInfo = new CartInfoPageletStruct();
        $this->shopmenu = new ShopmenuPageletStruct();
        $this->currency = new CurrencyPageletStruct();
        $this->navigation = new NavigationPageletStruct();
    }

    /**
     * @return CustomerOrderPageletStruct
     */
    public function getCustomerOrders(): CustomerOrderPageletStruct
    {
        return $this->customerOrders;
    }

    /**
     * @param CustomerOrderPageletStruct $customerOrders
     */
    public function setCustomerOrders(CustomerOrderPageletStruct $customerOrders): void
    {
        $this->customerOrders = $customerOrders;
    }
}
