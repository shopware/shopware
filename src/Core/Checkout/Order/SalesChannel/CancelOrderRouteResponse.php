<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * @extends StoreApiResponse<StateMachineStateEntity>
 */
#[Package('checkout')]
class CancelOrderRouteResponse extends StoreApiResponse
{
    public function getState(): StateMachineStateEntity
    {
        return $this->object;
    }
}
