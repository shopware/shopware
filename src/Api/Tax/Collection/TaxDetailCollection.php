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

    public function getProductIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getProducts()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getProducts(): ProductBasicCollection
    {
        $collection = new ProductBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getProducts()->getElements());
        }

        return $collection;
    }

    public function getAreaRuleIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getAreaRules()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
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
