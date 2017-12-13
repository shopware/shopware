<?php declare(strict_types=1);

namespace Shopware\Listing\Event\ListingSortingTranslation;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Listing\Definition\ListingSortingTranslationDefinition;

class ListingSortingTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'listing_sorting_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ListingSortingTranslationDefinition::class;
    }
}
