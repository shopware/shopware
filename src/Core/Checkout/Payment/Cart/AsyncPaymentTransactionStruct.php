<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

class AsyncPaymentTransactionStruct extends SyncPaymentTransactionStruct
{
    /**
     * @var string|null
     */
    private $returnUrl;

    public function __construct(OrderTransactionEntity $orderTransaction, string $returnUrl)
    {
        parent::__construct($orderTransaction);
        $this->returnUrl = $returnUrl;
    }

    public function getReturnUrl(): ?string
    {
        return $this->returnUrl;
    }
}
