<?php declare(strict_types=1);

namespace Shopware\Core\Application\Context\Event\ContextCartModifierTranslation;

use Shopware\Core\Application\Context\Definition\ContextCartModifierTranslationDefinition;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

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
