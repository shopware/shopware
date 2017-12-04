<?php declare(strict_types=1);

namespace Shopware\Customer\Collection;

use Shopware\Customer\Struct\CustomerGroupDetailStruct;

class CustomerGroupDetailCollection extends CustomerGroupBasicCollection
{
    /**
     * @var CustomerGroupDetailStruct[]
     */
    protected $elements = [];

    public function getCustomerUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getCustomers()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getCustomers(): CustomerBasicCollection
    {
        $collection = new CustomerBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getCustomers()->getElements());
        }

        return $collection;
    }

    public function getDiscountUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getDiscounts()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getDiscounts(): CustomerGroupDiscountBasicCollection
    {
        $collection = new CustomerGroupDiscountBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getDiscounts()->getElements());
        }

        return $collection;
    }

    public function getTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
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
