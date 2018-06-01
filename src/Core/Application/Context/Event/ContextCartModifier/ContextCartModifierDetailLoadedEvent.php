<?php declare(strict_types=1);

namespace Shopware\Core\Application\Context\Event\ContextCartModifier;

use Shopware\Core\Application\Context\Collection\ContextCartModifierDetailCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Rule\Event\ContextRuleBasicLoadedEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class ContextCartModifierDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'context_cart_modifier.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
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
