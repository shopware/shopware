<?php declare(strict_types=1);

namespace Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\Struct\ListingSortingTranslationSearchResult;

class ListingSortingTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'listing_sorting_translation.search.result.loaded';

    /**
     * @var \Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\Struct\ListingSortingTranslationSearchResult
     */
    protected $result;

    public function __construct(ListingSortingTranslationSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
