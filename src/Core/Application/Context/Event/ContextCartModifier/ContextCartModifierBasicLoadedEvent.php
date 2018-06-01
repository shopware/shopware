<?php declare(strict_types=1);

namespace Shopware\Core\Application\Context\Event\ContextCartModifier;

use Shopware\Core\Application\Context\Collection\ContextCartModifierBasicCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class ContextCartModifierBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'context_cart_modifier.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var ContextCartModifierBasicCollection
     */
    protected $contextCartModifiers;

    public function __construct(ContextCartModifierBasicCollection $contextCartModifiers, Context $context)
    {
        $this->context = $context;
        $this->contextCartModifiers = $contextCartModifiers;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getContextCartModifiers(): ContextCartModifierBasicCollection
    {
        return $this->contextCartModifiers;
    }
}
