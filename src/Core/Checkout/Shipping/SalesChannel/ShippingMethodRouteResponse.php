<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\SalesChannel;

use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class ShippingMethodRouteResponse extends StoreApiResponse
{
    /**
     * @var ShippingMethodCollection
     */
    protected $object;

    public function __construct(ShippingMethodCollection $shippingMethods)
    {
        parent::__construct($shippingMethods);
    }

    public function getShippingMethods(): ShippingMethodCollection
    {
        return $this->object;
    }
}
