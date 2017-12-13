<?php declare(strict_types=1);

namespace Shopware\Unit\Event\UnitTranslation;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Unit\Definition\UnitTranslationDefinition;

class UnitTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'unit_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return UnitTranslationDefinition::class;
    }
}
