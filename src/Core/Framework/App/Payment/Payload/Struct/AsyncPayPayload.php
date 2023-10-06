<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Payload\Struct;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\Recurring\RecurringDataStruct;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class AsyncPayPayload extends SyncPayPayload
{
    /**
     * @param mixed[] $requestData
     */
    public function __construct(
        OrderTransactionEntity $orderTransaction,
        OrderEntity $order,
        protected string $returnUrl,
        array $requestData = [],
        ?RecurringDataStruct $recurring = null,
    ) {
        parent::__construct($orderTransaction, $order, $requestData, $recurring);
    }

    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }
}
