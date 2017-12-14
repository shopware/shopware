<?php declare(strict_types=1);

namespace Shopware\Api\Tax\Collection;

use Shopware\Api\Product\Collection\ProductBasicCollection;
use Shopware\Api\Tax\Struct\TaxDetailStruct;

class TaxDetailCollection extends TaxBasicCollection
{
    /**
     * @var TaxDetailStruct[]
     */
    protected $elements = [];

    public function getProductUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getProducts()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getProducts(): ProductBasicCollection
    {
        $collection = new ProductBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getProducts()->getElements());
        }

        return $collection;
    }

    public function getAreaRuleUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getAreaRules()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getAreaRules(): TaxAreaRuleBasicCollection
    {
        $collection = new TaxAreaRuleBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getAreaRules()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return TaxDetailStruct::class;
    }
}
