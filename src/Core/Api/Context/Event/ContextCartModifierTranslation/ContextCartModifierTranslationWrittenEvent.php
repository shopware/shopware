<?php declare(strict_types=1);

namespace Shopware\Api\Context\Event\ContextCartModifierTranslation;

use Shopware\Api\Context\Definition\ContextCartModifierTranslationDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

class ContextCartModifierTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'context_cart_modifier_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ContextCartModifierTranslationDefinition::class;
    }
}
