<?php declare(strict_types=1);

namespace Shopware\Listing\Event\ListingSortingTranslation;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Listing\Struct\ListingSortingTranslationSearchResult;

class ListingSortingTranslationSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'listing_sorting_translation.search.result.loaded';

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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
