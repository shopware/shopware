<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderStateTranslation\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderState\Event\OrderStateBasicLoadedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderStateTranslation\Collection\OrderStateTranslationDetailCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Language\Event\LanguageBasicLoadedEvent;

class OrderStateTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'order_state_translation.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
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
