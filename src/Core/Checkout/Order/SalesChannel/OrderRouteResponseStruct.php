<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\Struct;

class OrderRouteResponseStruct extends Struct
{
    /**
     * @var EntitySearchResult
     */
    protected $orders;

    /**
     * @var array
     */
    protected $paymentChangeable = [];

    public function __construct(EntitySearchResult $orders, $paymentChangeable = [])
    {
        $this->orders = $orders;
        $this->paymentChangeable = $paymentChangeable;
    }

    public function getApiAlias(): string
    {
        return 'order-route-response-struct';
    }

    public function getOrders(): EntitySearchResult
    {
        return $this->orders;
    }

    public function getPaymentChangeable(string $orderId): bool
    {
        return $this->paymentChangeable[$orderId] ?? true;
    }

    public function getPaymentsChangeable(): array
    {
        return $this->paymentChangeable;
    }
}
