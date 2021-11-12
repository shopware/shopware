<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Payload\Struct;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;

/**
 * @internal only for use by the app-system
 */
class AsyncPayPayload extends SyncPayPayload
{
    protected string $returnUrl;

    protected array $requestData;

    public function __construct(OrderTransactionEntity $orderTransaction, OrderEntity $order, string $returnUrl, array $requestData)
    {
        parent::__construct($orderTransaction, $order);
        $this->returnUrl = $returnUrl;
        $this->requestData = $requestData;
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
