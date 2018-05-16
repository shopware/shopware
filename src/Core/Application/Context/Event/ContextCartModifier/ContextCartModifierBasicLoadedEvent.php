<?php declare(strict_types=1);

namespace Shopware\Application\Context\Event\ContextCartModifier;

use Shopware\Application\Context\Collection\ContextCartModifierBasicCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ContextCartModifierBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'context_cart_modifier.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ContextCartModifierBasicCollection
     */
    protected $contextCartModifiers;

    public function __construct(ContextCartModifierBasicCollection $contextCartModifiers, ApplicationContext $context)
    {
        $this->context = $context;
        $this->contextCartModifiers = $contextCartModifiers;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getContextCartModifiers(): ContextCartModifierBasicCollection
    {
        return $this->contextCartModifiers;
    }
}
