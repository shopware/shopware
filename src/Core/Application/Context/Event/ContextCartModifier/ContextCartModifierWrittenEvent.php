<?php declare(strict_types=1);

namespace Shopware\Application\Context\Event\ContextCartModifier;

use Shopware\Application\Context\Definition\ContextCartModifierDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

class ContextCartModifierWrittenEvent extends WrittenEvent
{
    public const NAME = 'context_cart_modifier.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ContextCartModifierDefinition::class;
    }
}
