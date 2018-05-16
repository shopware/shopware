<?php declare(strict_types=1);

namespace Shopware\Application\Context\Event\ContextCartModifier;

use Shopware\Application\Context\Definition\ContextCartModifierDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

class ContextCartModifierDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'context_cart_modifier.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ContextCartModifierDefinition::class;
    }
}
