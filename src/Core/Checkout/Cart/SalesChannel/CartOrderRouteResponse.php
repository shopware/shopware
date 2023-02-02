<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class CartOrderRouteResponse extends StoreApiResponse
{
    /**
     * @var OrderEntity
     */
    protected $object;

    public function __construct(OrderEntity $object)
    {
        parent::__construct($object);
    }

    public function getOrder(): OrderEntity
    {
        return $this->object;
    }
}
