<?php declare(strict_types=1);

namespace Shopware\Api\Context\Event\ContextCartModifier;

use Shopware\Api\Context\Definition\ContextCartModifierDefinition;
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
