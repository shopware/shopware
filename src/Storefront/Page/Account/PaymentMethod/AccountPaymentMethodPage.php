<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\PaymentMethod;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Storefront\Page\Page;

class AccountPaymentMethodPage extends Page
{
    /**
     * @var EntitySearchResult
     */
    protected $paymentMethods;

    public function getPaymentMethods(): EntitySearchResult
    {
        return $this->paymentMethods;
    }

    public function setPaymentMethods(EntitySearchResult $paymentMethods): void
    {
        $this->paymentMethods = $paymentMethods;
    }
}
