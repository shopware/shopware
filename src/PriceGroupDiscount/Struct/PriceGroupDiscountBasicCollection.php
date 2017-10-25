<?php declare(strict_types=1);

namespace Shopware\PriceGroupDiscount\Struct;

use Shopware\Framework\Struct\Collection;

class PriceGroupDiscountBasicCollection extends Collection
{
    /**
     * @var PriceGroupDiscountBasicStruct[]
     */
    protected $elements = [];

    public function add(PriceGroupDiscountBasicStruct $priceGroupDiscount): void
    {
        $key = $this->getKey($priceGroupDiscount);
        $this->elements[$key] = $priceGroupDiscount;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(PriceGroupDiscountBasicStruct $priceGroupDiscount): void
    {
        parent::doRemoveByKey($this->getKey($priceGroupDiscount));
    }

    public function exists(PriceGroupDiscountBasicStruct $priceGroupDiscount): bool
    {
        return parent::has($this->getKey($priceGroupDiscount));
    }

    public function getList(array $uuids): PriceGroupDiscountBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? PriceGroupDiscountBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (PriceGroupDiscountBasicStruct $priceGroupDiscount) {
            return $priceGroupDiscount->getUuid();
        });
    }

    public function merge(PriceGroupDiscountBasicCollection $collection)
    {
        /** @var PriceGroupDiscountBasicStruct $priceGroupDiscount */
        foreach ($collection as $priceGroupDiscount) {
            if ($this->has($this->getKey($priceGroupDiscount))) {
                continue;
            }
            $this->add($priceGroupDiscount);
        }
    }

    public function getPriceGroupUuids(): array
    {
        return $this->fmap(function (PriceGroupDiscountBasicStruct $priceGroupDiscount) {
            return $priceGroupDiscount->getPriceGroupUuid();
        });
    }

    public function filterByPriceGroupUuid(string $uuid): PriceGroupDiscountBasicCollection
    {
        return $this->filter(function (PriceGroupDiscountBasicStruct $priceGroupDiscount) use ($uuid) {
            return $priceGroupDiscount->getPriceGroupUuid() === $uuid;
        });
    }

    public function getCustomerGroupUuids(): array
    {
        return $this->fmap(function (PriceGroupDiscountBasicStruct $priceGroupDiscount) {
            return $priceGroupDiscount->getCustomerGroupUuid();
        });
    }

    public function filterByCustomerGroupUuid(string $uuid): PriceGroupDiscountBasicCollection
    {
        return $this->filter(function (PriceGroupDiscountBasicStruct $priceGroupDiscount) use ($uuid) {
            return $priceGroupDiscount->getCustomerGroupUuid() === $uuid;
        });
    }

    public function current(): PriceGroupDiscountBasicStruct
    {
        return parent::current();
    }

    protected function getKey(PriceGroupDiscountBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
