<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderStateTranslation\Collection;

use Shopware\Application\Language\Collection\LanguageBasicCollection;
use Shopware\Checkout\Order\Aggregate\OrderState\Collection\OrderStateBasicCollection;
use Shopware\Checkout\Order\Aggregate\OrderStateTranslation\Struct\OrderStateTranslationDetailStruct;

class OrderStateTranslationDetailCollection extends OrderStateTranslationBasicCollection
{
    /**
     * @var \Shopware\Checkout\Order\Aggregate\OrderStateTranslation\Struct\OrderStateTranslationDetailStruct[]
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

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
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
