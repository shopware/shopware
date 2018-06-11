<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\Event;

use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\System\Listing\Definition\ListingSortingTranslationDefinition;

class ListingSortingTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'listing_sorting_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ListingSortingTranslationDefinition::class;
    }
}
