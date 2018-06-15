<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderStateTranslation\Collection;

use Shopware\Core\Checkout\Order\Aggregate\OrderState\Collection\OrderStateBasicCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderStateTranslation\Struct\OrderStateTranslationDetailStruct;
use Shopware\Core\System\Language\Collection\LanguageBasicCollection;

class OrderStateTranslationDetailCollection extends OrderStateTranslationBasicCollection
{
    /**
     * @var \Shopware\Core\Checkout\Order\Aggregate\OrderStateTranslation\Struct\OrderStateTranslationDetailStruct[]
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
