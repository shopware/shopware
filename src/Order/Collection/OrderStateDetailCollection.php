<?php declare(strict_types=1);

namespace Shopware\Order\Collection;

use Shopware\Mail\Collection\MailBasicCollection;
use Shopware\Order\Struct\OrderStateDetailStruct;

class OrderStateDetailCollection extends OrderStateBasicCollection
{
    /**
     * @var OrderStateDetailStruct[]
     */
    protected $elements = [];

    public function getMailUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getMails()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getMails(): MailBasicCollection
    {
        $collection = new MailBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getMails()->getElements());
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

    public function getOrderDeliveryUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getOrderDeliveries()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getOrderDeliveries(): OrderDeliveryBasicCollection
    {
        $collection = new OrderDeliveryBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getOrderDeliveries()->getElements());
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

    public function getTranslations(): OrderStateTranslationBasicCollection
    {
        $collection = new OrderStateTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return OrderStateDetailStruct::class;
    }
}
