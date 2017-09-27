<?php declare(strict_types=1);

namespace Shopware\Currency\Struct;

use Shopware\Framework\Struct\Collection;

class CurrencyBasicCollection extends Collection
{
    /**
     * @var CurrencyBasicStruct[]
     */
    protected $elements = [];

    public function add(CurrencyBasicStruct $currency): void
    {
        $key = $this->getKey($currency);
        $this->elements[$key] = $currency;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(CurrencyBasicStruct $currency): void
    {
        parent::doRemoveByKey($this->getKey($currency));
    }

    public function exists(CurrencyBasicStruct $currency): bool
    {
        return parent::has($this->getKey($currency));
    }

    public function getList(array $uuids): CurrencyBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? CurrencyBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (CurrencyBasicStruct $currency) {
            return $currency->getUuid();
        });
    }

    public function merge(CurrencyBasicCollection $collection)
    {
        /** @var CurrencyBasicStruct $currency */
        foreach ($collection as $currency) {
            if ($this->has($this->getKey($currency))) {
                continue;
            }
            $this->add($currency);
        }
    }

    public function sortByPosition(): CurrencyBasicCollection
    {
        $this->sort(function (CurrencyBasicStruct $a, CurrencyBasicStruct $b) {
            return $a->getPosition() <=> $b->getPosition();
        });

        return $this;
    }

    protected function getKey(CurrencyBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
