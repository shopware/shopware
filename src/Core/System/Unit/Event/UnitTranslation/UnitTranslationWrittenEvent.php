<?php declare(strict_types=1);

namespace Shopware\System\Unit\Event\UnitTranslation;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Unit\Definition\UnitTranslationDefinition;

class UnitTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'unit_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return UnitTranslationDefinition::class;
    }
}
