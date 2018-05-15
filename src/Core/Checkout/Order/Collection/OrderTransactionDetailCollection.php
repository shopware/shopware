<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Collection;

use Shopware\Checkout\Order\Struct\OrderTransactionDetailStruct;
use Shopware\Checkout\Payment\Collection\PaymentMethodBasicCollection;

class OrderTransactionDetailCollection extends OrderTransactionBasicCollection
{
    /**
     * @var OrderTransactionDetailStruct[]
     */
    protected $elements = [];

    public function getPaymentMethods(): PaymentMethodBasicCollection
    {
        return new PaymentMethodBasicCollection(
            $this->fmap(function (OrderTransactionDetailStruct $orderTransaction) {
                return $orderTransaction->getPaymentMethod();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return OrderTransactionDetailStruct::class;
    }
}
