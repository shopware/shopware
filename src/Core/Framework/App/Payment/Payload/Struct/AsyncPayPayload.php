<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Payload\Struct;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class AsyncPayPayload extends SyncPayPayload
{
    public function __construct(
        OrderTransactionEntity $orderTransaction,
        OrderEntity $order,
        protected string $returnUrl,
        protected array $requestData
    ) {
        parent::__construct($orderTransaction, $order);
    }

    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }

    public function getRequestData(): array
    {
        return $this->requestData;
    }
}
