<?php declare(strict_types=1);

namespace Shopware\Api\Order\Collection;

use Shopware\Api\Mail\Collection\MailBasicCollection;
use Shopware\Api\Order\Struct\OrderStateDetailStruct;

class OrderStateDetailCollection extends OrderStateBasicCollection
{
    /**
     * @var OrderStateDetailStruct[]
     */
    protected $elements = [];

    public function getMailIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getMails()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getMails(): MailBasicCollection
    {
        $collection = new MailBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getMails()->getElements());
        }

        return $collection;
    }

    public function getOrderIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getOrders()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getOrders(): OrderBasicCollection
    {
        $collection = new OrderBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getOrders()->getElements());
        }

        return $collection;
    }

    public function getOrderDeliveryIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getOrderDeliveries()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getOrderDeliveries(): OrderDeliveryBasicCollection
    {
        $collection = new OrderDeliveryBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getOrderDeliveries()->getElements());
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
