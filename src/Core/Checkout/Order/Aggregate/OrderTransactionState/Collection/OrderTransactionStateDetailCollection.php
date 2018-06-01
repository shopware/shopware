<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\Collection;

use Shopware\Core\Checkout\Order\Aggregate\OrderStateTranslation\Collection\OrderStateTranslationBasicCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\Struct\OrderTransactionStateDetailStruct;

class OrderTransactionStateDetailCollection extends OrderTransactionStatebasicCollection
{
    /**
     * @var \Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\Struct\OrderTransactionStateDetailStruct[]
     */
    protected $elements = [];

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
        return OrderTransactionStateDetailStruct::class;
    }
}
