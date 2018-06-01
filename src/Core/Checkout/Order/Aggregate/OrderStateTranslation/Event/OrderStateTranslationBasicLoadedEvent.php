<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderStateTranslation\Event;

use Shopware\Framework\Context;
use Shopware\Checkout\Order\Aggregate\OrderStateTranslation\Collection\OrderStateTranslationBasicCollection;
use Shopware\Framework\Event\NestedEvent;

class OrderStateTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'order_state_translation.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\Checkout\Order\Aggregate\OrderStateTranslation\Collection\OrderStateTranslationBasicCollection
     */
    protected $orderStateTranslations;

    public function __construct(OrderStateTranslationBasicCollection $orderStateTranslations, Context $context)
    {
        $this->context = $context;
        $this->orderStateTranslations = $orderStateTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrderStateTranslations(): OrderStateTranslationBasicCollection
    {
        return $this->orderStateTranslations;
    }
}
