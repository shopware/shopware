<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderStateTranslation\Event;

use Shopware\Framework\Context;
use Shopware\Application\Language\Event\LanguageBasicLoadedEvent;
use Shopware\Checkout\Order\Aggregate\OrderState\Event\OrderStateBasicLoadedEvent;
use Shopware\Checkout\Order\Aggregate\OrderStateTranslation\Collection\OrderStateTranslationDetailCollection;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class OrderStateTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'order_state_translation.detail.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var OrderStateTranslationDetailCollection
     */
    protected $orderStateTranslations;

    public function __construct(OrderStateTranslationDetailCollection $orderStateTranslations, Context $context)
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

    public function getOrderStateTranslations(): OrderStateTranslationDetailCollection
    {
        return $this->orderStateTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->orderStateTranslations->getOrderStates()->count() > 0) {
            $events[] = new OrderStateBasicLoadedEvent($this->orderStateTranslations->getOrderStates(), $this->context);
        }
        if ($this->orderStateTranslations->getLanguages()->count() > 0) {
            $events[] = new LanguageBasicLoadedEvent($this->orderStateTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
