<?php declare(strict_types=1);

namespace Shopware\Api\Context\Event\ContextRule;

use Shopware\Api\Context\Collection\ContextCartModifierBasicCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

class ContextCartModifierBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'context_cart_modifier.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var ContextCartModifierBasicCollection
     */
    protected $contextCartModifiers;

    public function __construct(ContextCartModifierBasicCollection $contextCartModifiers, ShopContext $context)
    {
        $this->context = $context;
        $this->contextCartModifiers = $contextCartModifiers;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getContextCartModifiers(): ContextCartModifierBasicCollection
    {
        return $this->contextCartModifiers;
    }
}
