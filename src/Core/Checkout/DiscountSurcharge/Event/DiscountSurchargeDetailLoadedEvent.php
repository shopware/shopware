<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Event;

use Shopware\Core\Checkout\DiscountSurcharge\Collection\DiscountSurchargeDetailCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Rule\Event\ContextRuleBasicLoadedEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class DiscountSurchargeDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'discount_surcharge.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var DiscountSurchargeDetailCollection
     */
    protected $discountSurcharges;

    public function __construct(DiscountSurchargeDetailCollection $discountSurcharges, Context $context)
    {
        $this->discountSurcharges = $discountSurcharges;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getDiscountSurcharges(): DiscountSurchargeDetailCollection
    {
        return $this->discountSurcharges;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->discountSurcharges->getContextRules()->count() > 0) {
            $events[] = new ContextRuleBasicLoadedEvent($this->discountSurcharges->getContextRules(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
