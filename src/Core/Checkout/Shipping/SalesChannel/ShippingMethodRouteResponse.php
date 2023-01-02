<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\SalesChannel;

use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('checkout')]
class ShippingMethodRouteResponse extends StoreApiResponse
{
    /**
     * @var EntitySearchResult
     */
    protected $object;

    public function __construct(EntitySearchResult $shippingMethods)
    {
        parent::__construct($shippingMethods);
    }

    public function getShippingMethods(): ShippingMethodCollection
    {
        /** @var ShippingMethodCollection $collection */
        $collection = $this->object->getEntities();

        return $collection;
    }
}
