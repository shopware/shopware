<?php declare(strict_types=1);

namespace Shopware\Api\Currency\Collection;

use Shopware\Api\Currency\Struct\CurrencyDetailStruct;
use Shopware\Api\Order\Collection\OrderBasicCollection;
use Shopware\Api\Shop\Collection\ShopBasicCollection;

class CurrencyDetailCollection extends CurrencyBasicCollection
{
    /**
     * @var CurrencyDetailStruct[]
     */
    protected $elements = [];

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

    public function getTranslations(): CurrencyTranslationBasicCollection
    {
        $collection = new CurrencyTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    public function getOrderUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getOrders()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getOrders(): OrderBasicCollection
    {
        $collection = new OrderBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getOrders()->getElements());
        }

        return $collection;
    }

    public function getAllShopUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getShopUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getAllShops(): ShopBasicCollection
    {
        $collection = new ShopBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getShops()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return CurrencyDetailStruct::class;
    }
}
