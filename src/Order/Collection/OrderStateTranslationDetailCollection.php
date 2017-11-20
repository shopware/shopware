<?php declare(strict_types=1);

namespace Shopware\Order\Collection;

use Shopware\Order\Struct\OrderStateTranslationDetailStruct;
use Shopware\Shop\Collection\ShopBasicCollection;

class OrderStateTranslationDetailCollection extends OrderStateTranslationBasicCollection
{
    /**
     * @var OrderStateTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getOrderStates(): OrderStateBasicCollection
    {
        return new OrderStateBasicCollection(
            $this->fmap(function (OrderStateTranslationDetailStruct $orderStateTranslation) {
                return $orderStateTranslation->getOrderState();
            })
        );
    }

    public function getLanguages(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (OrderStateTranslationDetailStruct $orderStateTranslation) {
                return $orderStateTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return OrderStateTranslationDetailStruct::class;
    }
}
