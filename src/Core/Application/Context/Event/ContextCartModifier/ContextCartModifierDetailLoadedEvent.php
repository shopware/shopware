<?php declare(strict_types=1);

namespace Shopware\Application\Context\Event\ContextCartModifier;

use Shopware\Application\Context\Collection\ContextCartModifierDetailCollection;
use Shopware\Framework\Context;
use Shopware\Checkout\Rule\Event\ContextRuleBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ContextCartModifierDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'context_cart_modifier.detail.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var ContextCartModifierDetailCollection
     */
    protected $contextCartModifiers;

    public function __construct(ContextCartModifierDetailCollection $contextCartModifiers, Context $context)
    {
        $this->contextCartModifiers = $contextCartModifiers;
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

    public function getContextCartModifiers(): ContextCartModifierDetailCollection
    {
        return $this->contextCartModifiers;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->contextCartModifiers->getContextRules()->count() > 0) {
            $events[] = new ContextRuleBasicLoadedEvent($this->contextCartModifiers->getContextRules(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
