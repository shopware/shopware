<?php declare(strict_types=1);

namespace Shopware\Application\Context\Event\ContextCartModifier;

use Shopware\Application\Context\Collection\ContextCartModifierDetailCollection;
use Shopware\Checkout\Rule\Event\ContextRuleBasicLoadedEvent;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ContextCartModifierDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'context_cart_modifier.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ContextCartModifierDetailCollection
     */
    protected $contextCartModifiers;

    public function __construct(ContextCartModifierDetailCollection $contextCartModifiers, ApplicationContext $context)
    {
        $this->contextCartModifiers = $contextCartModifiers;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
