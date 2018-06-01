<?php declare(strict_types=1);

namespace Shopware\Core\Application\Context\Event\ContextCartModifierTranslation;

use Shopware\Core\Application\Context\Definition\ContextCartModifierTranslationDefinition;
use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

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
