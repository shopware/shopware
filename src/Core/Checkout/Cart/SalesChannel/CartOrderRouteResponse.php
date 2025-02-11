<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<OrderEntity>
 */
#[Package('checkout')]
class CartOrderRouteResponse extends StoreApiResponse
{
    public function getOrder(): OrderEntity
    {
        return $this->object;
    }
}
