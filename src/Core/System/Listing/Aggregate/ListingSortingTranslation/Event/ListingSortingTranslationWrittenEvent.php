<?php declare(strict_types=1);

namespace Shopware\System\Listing\Aggregate\ListingSortingTranslation\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Listing\Definition\ListingSortingTranslationDefinition;

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
