<?php declare(strict_types=1);

namespace Shopware\System\Listing\Event\ListingSortingTranslation;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\System\Listing\Definition\ListingSortingTranslationDefinition;

class ListingSortingTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'listing_sorting_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ListingSortingTranslationDefinition::class;
    }
}
