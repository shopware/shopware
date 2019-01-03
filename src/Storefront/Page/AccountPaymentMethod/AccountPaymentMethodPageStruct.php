<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\AccountPaymentMethod;

use Shopware\Core\Framework\Struct\Struct;
use Shopware\Storefront\Pagelet\CartInfo\CartInfoPageletStruct;
use Shopware\Storefront\Pagelet\CheckoutPaymentMethod\PaymentMethodPageletStruct;
use Shopware\Storefront\Pagelet\Currency\CurrencyPageletStruct;
use Shopware\Storefront\Pagelet\Header\HeaderPageletStructTrait;
use Shopware\Storefront\Pagelet\Language\LanguagePageletStruct;
use Shopware\Storefront\Pagelet\Navigation\NavigationPageletStruct;
use Shopware\Storefront\Pagelet\Shopmenu\ShopmenuPageletStruct;

class AccountPaymentMethodPageStruct extends Struct
{
    use HeaderPageletStructTrait;

    /**
     * @var PaymentMethodPageletStruct
     */
    protected $PaymentMethod;

    public function __construct()
    {
        $this->PaymentMethod = new PaymentMethodPageletStruct();
        $this->language = new LanguagePageletStruct();
        $this->cartInfo = new CartInfoPageletStruct();
        $this->shopmenu = new ShopmenuPageletStruct();
        $this->currency = new CurrencyPageletStruct();
        $this->navigation = new NavigationPageletStruct();
    }

    /**
     * @return PaymentMethodPageletStruct
     */
    public function getPaymentMethod(): PaymentMethodPageletStruct
    {
        return $this->PaymentMethod;
    }

    /**
     * @param PaymentMethodPageletStruct $PaymentMethod
     */
    public function setPaymentMethod(PaymentMethodPageletStruct $PaymentMethod): void
    {
        $this->PaymentMethod = $PaymentMethod;
    }
}
