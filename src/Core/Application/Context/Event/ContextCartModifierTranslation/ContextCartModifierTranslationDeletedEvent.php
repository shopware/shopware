<?php declare(strict_types=1);

namespace Shopware\Application\Context\Event\ContextCartModifierTranslation;

use Shopware\Application\Context\Definition\ContextCartModifierTranslationDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

class ContextCartModifierTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'context_cart_modifier_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ContextCartModifierTranslationDefinition::class;
    }
}
