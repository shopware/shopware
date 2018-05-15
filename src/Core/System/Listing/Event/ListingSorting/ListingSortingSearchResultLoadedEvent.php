<?php declare(strict_types=1);

namespace Shopware\System\Listing\Event\ListingSorting;

use Shopware\System\Listing\Struct\ListingSortingSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ListingSortingSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'listing_sorting.search.result.loaded';

    /**
     * @var ListingSortingSearchResult
     */
    protected $result;

    public function __construct(ListingSortingSearchResult $result)
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
