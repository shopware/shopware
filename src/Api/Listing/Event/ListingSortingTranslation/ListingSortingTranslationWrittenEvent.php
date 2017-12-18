<?php declare(strict_types=1);

namespace Shopware\Api\Listing\Event\ListingSortingTranslation;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Listing\Definition\ListingSortingTranslationDefinition;

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
