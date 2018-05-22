<?php declare(strict_types=1);

namespace Shopware\System\Listing\Aggregate\ListingSortingTranslation\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Listing\Aggregate\ListingSortingTranslation\Struct\ListingSortingTranslationSearchResult;

class ListingSortingTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'listing_sorting_translation.search.result.loaded';

    /**
     * @var \Shopware\System\Listing\Aggregate\ListingSortingTranslation\Struct\ListingSortingTranslationSearchResult
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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
