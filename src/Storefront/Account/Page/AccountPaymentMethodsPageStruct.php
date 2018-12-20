<?php declare(strict_types=1);

namespace Shopware\Storefront\Account\Page;

use Shopware\Storefront\Checkout\Page\CartInfoPageletStruct;
use Shopware\Storefront\Checkout\Page\PaymentMethodPageletStruct;
use Shopware\Storefront\Content\Page\CurrencyPageletStruct;
use Shopware\Storefront\Content\Page\HeaderPageletTrait;
use Shopware\Storefront\Content\Page\LanguagePageletStruct;
use Shopware\Storefront\Content\Page\ShopmenuPageletStruct;
use Shopware\Storefront\Framework\Page\PageStruct;
use Shopware\Storefront\Listing\Page\NavigationPageletStruct;

class AccountPaymentMethodsPageStruct extends PageStruct
{
    use HeaderPageletTrait;

    /**
     * @var PaymentMethodPageletStruct
     */
    protected $paymentMethods;

    public function __construct()
    {
        $this->paymentMethods = new PaymentMethodPageletStruct();
        $this->language = new LanguagePageletStruct();
        $this->cartInfo = new CartInfoPageletStruct();
        $this->shopmenu = new ShopmenuPageletStruct();
        $this->currency = new CurrencyPageletStruct();
        $this->navigation = new NavigationPageletStruct();
    }

    /**
     * @return PaymentMethodPageletStruct
     */
    public function getPaymentMethods(): PaymentMethodPageletStruct
    {
        return $this->paymentMethods;
    }

    /**
     * @param PaymentMethodPageletStruct $paymentMethods
     */
    public function setPaymentMethods(PaymentMethodPageletStruct $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }
}
