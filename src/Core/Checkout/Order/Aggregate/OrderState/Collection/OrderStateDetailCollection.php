<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderState\Collection;

use Shopware\Core\Checkout\Order\Aggregate\OrderState\Struct\OrderStateDetailStruct;
use Shopware\Core\Checkout\Order\Aggregate\OrderStateTranslation\Collection\OrderStateTranslationBasicCollection;

class OrderStateDetailCollection extends OrderStateBasicCollection
{
    /**
     * @var \Shopware\Core\Checkout\Order\Aggregate\OrderState\Struct\OrderStateDetailStruct[]
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
        return OrderStateDetailStruct::class;
    }
}
