<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

#[Package('customer-order')]
class CancelOrderRouteResponse extends StoreApiResponse
{
    /**
     * @var StateMachineStateEntity
     */
    protected $object;

    public function __construct(StateMachineStateEntity $object)
    {
        parent::__construct($object);
    }

    public function getState(): StateMachineStateEntity
    {
        return $this->object;
    }
}
