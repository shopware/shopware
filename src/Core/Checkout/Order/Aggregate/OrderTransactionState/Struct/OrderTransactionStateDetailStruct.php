<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\Struct;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation\Collection\OrderTransactionStateTranslationBasicCollection;

class OrderTransactionStateDetailStruct extends OrderTransactionStateBasicStruct
{
    /**
     * @var OrderTransactionStateTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->translations = new OrderTransactionStateTranslationBasicCollection();
    }

    public function getTranslations(): OrderTransactionStateTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(OrderTransactionStateTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
