<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderTransaction\Collection;

use Shopware\Checkout\Order\Aggregate\OrderTransaction\Struct\OrderTransactionDetailStruct;
use Shopware\Checkout\Payment\Collection\PaymentMethodBasicCollection;

class OrderTransactionDetailCollection extends OrderTransactionBasicCollection
{
    /**
     * @var \Shopware\Checkout\Order\Aggregate\OrderTransaction\Struct\OrderTransactionDetailStruct[]
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
