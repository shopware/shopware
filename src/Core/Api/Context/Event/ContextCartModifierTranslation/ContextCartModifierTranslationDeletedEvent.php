<?php declare(strict_types=1);

namespace Shopware\Api\Context\Event\ContextCartModifierTranslation;

use Shopware\Api\Context\Definition\ContextCartModifierTranslationDefinition;
use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;

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
