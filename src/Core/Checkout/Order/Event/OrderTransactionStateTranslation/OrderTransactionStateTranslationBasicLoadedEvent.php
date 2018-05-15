<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event\OrderTransactionStateTranslation;

use Shopware\Checkout\Order\Collection\OrderTransactionStateTranslationBasicCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class OrderTransactionStateTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'order_transaction_state_translation.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var OrderTransactionStateTranslationBasicCollection
     */
    protected $orderTransactionStateTranslations;

    public function __construct(OrderTransactionStateTranslationBasicCollection $orderTransactionStateTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->orderTransactionStateTranslations = $orderTransactionStateTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getOrderTransactionStateTranslations(): OrderTransactionStateTranslationBasicCollection
    {
        return $this->orderTransactionStateTranslations;
    }
}
