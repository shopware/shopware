<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit\Aggregate\UnitTranslation\Event;

use Shopware\Core\Framework\ORM\Write\WrittenEvent;
use Shopware\Core\System\Unit\Definition\UnitTranslationDefinition;

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
