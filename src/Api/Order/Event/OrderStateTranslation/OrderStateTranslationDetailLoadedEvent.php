<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderStateTranslation;

use Shopware\Api\Order\Collection\OrderStateTranslationDetailCollection;
use Shopware\Api\Order\Event\OrderState\OrderStateBasicLoadedEvent;
use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class OrderStateTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'order_state_translation.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var OrderStateTranslationDetailCollection
     */
    protected $orderStateTranslations;

    public function __construct(OrderStateTranslationDetailCollection $orderStateTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->orderStateTranslations = $orderStateTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
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
            $events[] = new ShopBasicLoadedEvent($this->orderStateTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
