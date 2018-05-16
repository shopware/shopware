<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderState\Struct;


use Shopware\Checkout\Order\Aggregate\OrderStateTranslation\Collection\OrderStateTranslationBasicCollection;

class OrderStateDetailStruct extends OrderStateBasicStruct
{
    /**
     * @var \Shopware\Checkout\Order\Aggregate\OrderStateTranslation\Collection\OrderStateTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->translations = new OrderStateTranslationBasicCollection();
    }

    public function getTranslations(): OrderStateTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(OrderStateTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
