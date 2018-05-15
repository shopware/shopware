<?php declare(strict_types=1);

namespace Shopware\System\Unit\Event\UnitTranslation;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\System\Unit\Definition\UnitTranslationDefinition;

class UnitTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'unit_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return UnitTranslationDefinition::class;
    }
}
