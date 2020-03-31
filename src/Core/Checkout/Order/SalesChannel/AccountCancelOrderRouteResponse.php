<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use Shopware\Core\System\SalesChannel\StoreApiResponse;

class AccountCancelOrderRouteResponse extends StoreApiResponse
{
    /**
     * @var Struct
     */
    protected $object;

    public function getStateMachineState(): Struct
    {
        return $this->object;
    }
}
