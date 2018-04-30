<?php declare(strict_types=1);

namespace Shopware\Api\Listing\Event\ListingSortingTranslation;

use Shopware\Api\Listing\Struct\ListingSortingTranslationSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ListingSortingTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'listing_sorting_translation.search.result.loaded';

    /**
     * @var ListingSortingTranslationSearchResult
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
