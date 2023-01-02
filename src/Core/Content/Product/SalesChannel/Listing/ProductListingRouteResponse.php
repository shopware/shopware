<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('inventory')]
class ProductListingRouteResponse extends StoreApiResponse
{
    /**
     * @var ProductListingResult
     */
    protected $object;

    public function __construct(ProductListingResult $object)
    {
        parent::__construct($object);
    }

    public function getResult(): ProductListingResult
    {
        return $this->object;
    }
}
