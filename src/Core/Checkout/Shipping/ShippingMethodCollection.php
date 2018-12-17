<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping;

use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ShippingMethodCollection extends EntityCollection
{
    /**
     * @var ShippingMethodEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? ShippingMethodEntity
    {
        return parent::get($id);
    }

    public function current(): ShippingMethodEntity
    {
        return parent::current();
    }

    public function getPriceIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getPrices()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getPrices(): ShippingMethodPriceCollection
    {
        $collection = new ShippingMethodPriceCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getPrices()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return ShippingMethodEntity::class;
    }
}
