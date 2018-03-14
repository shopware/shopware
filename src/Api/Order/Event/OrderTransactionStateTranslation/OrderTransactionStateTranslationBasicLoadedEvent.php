<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderTransactionStateTranslation;

use Shopware\Api\Order\Collection\OrderTransactionStateTranslationBasicCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

class OrderTransactionStateTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'order_transaction_state_translation.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var OrderTransactionStateTranslationBasicCollection
     */
    protected $orderTransactionStateTranslations;

    public function __construct(OrderTransactionStateTranslationBasicCollection $orderTransactionStateTranslations, ShopContext $context)
    {
        $this->context = $context;
        $this->orderTransactionStateTranslations = $orderTransactionStateTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getOrderTransactionStateTranslations(): OrderTransactionStateTranslationBasicCollection
    {
        return $this->orderTransactionStateTranslations;
    }
}
