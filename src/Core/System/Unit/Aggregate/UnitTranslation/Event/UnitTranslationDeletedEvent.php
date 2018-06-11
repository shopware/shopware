<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit\Aggregate\UnitTranslation\Event;

use Shopware\Core\Framework\ORM\Event\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\System\Unit\Definition\UnitTranslationDefinition;

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
