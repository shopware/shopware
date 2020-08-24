<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Detail;

use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

class ProductDetailRouteResponse extends StoreApiResponse
{
    /**
     * @var ArrayStruct
     */
    protected $object;

    public function __construct(SalesChannelProductEntity $product, ?PropertyGroupCollection $configurator)
    {
        parent::__construct(new ArrayStruct([
            'product' => $product,
            'configurator' => $configurator,
        ], 'product_detail'));
    }

    public function getResult(): ArrayStruct
    {
        return $this->object;
    }

    public function getProduct(): SalesChannelProductEntity
    {
        return $this->object->get('product');
    }

    public function getConfigurator(): ?PropertyGroupCollection
    {
        return $this->object->get('configurator');
    }
}
