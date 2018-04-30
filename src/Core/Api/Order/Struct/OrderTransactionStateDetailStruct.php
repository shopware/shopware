<?php declare(strict_types=1);

namespace Shopware\Api\Order\Struct;

use Shopware\Api\Order\Collection\OrderTransactionStateTranslationBasicCollection;

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
