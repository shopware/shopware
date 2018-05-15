<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Collection;

use Shopware\Checkout\Customer\Struct\CustomerGroupDetailStruct;

class CustomerGroupDetailCollection extends CustomerGroupBasicCollection
{
    /**
     * @var CustomerGroupDetailStruct[]
     */
    protected $elements = [];

    public function getDiscountIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getDiscounts()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getDiscounts(): CustomerGroupDiscountBasicCollection
    {
        $collection = new CustomerGroupDiscountBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getDiscounts()->getElements());
        }

        return $collection;
    }

    public function getTranslationIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getTranslations(): CustomerGroupTranslationBasicCollection
    {
        $collection = new CustomerGroupTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return CustomerGroupDetailStruct::class;
    }
}
