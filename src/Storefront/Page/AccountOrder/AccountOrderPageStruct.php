<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountOrder;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletStruct;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletStruct;
use Shopware\Storefront\Pagelet\Header\HeaderPageletStructTrait;
use Shopware\Storefront\Pagelet\Language\LanguagePageletStruct;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletStruct;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletStruct;

class AccountOrderPageStruct extends Struct
{
    use HeaderPageletStructTrait;

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
