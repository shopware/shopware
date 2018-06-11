<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\Event;

use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;
use Shopware\Core\System\Listing\Definition\ListingSortingTranslationDefinition;

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
