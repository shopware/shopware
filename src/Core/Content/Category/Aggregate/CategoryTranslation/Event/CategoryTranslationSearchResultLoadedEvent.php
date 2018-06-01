<?php declare(strict_types=1);

namespace Shopware\Content\Category\Aggregate\CategoryTranslation\Event;

use Shopware\Framework\Context;
use Shopware\Content\Category\Aggregate\CategoryTranslation\Struct\CategoryTranslationSearchResult;
use Shopware\Framework\Event\NestedEvent;

class CategoryTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'category_translation.search.result.loaded';

    /**
     * @var CategoryTranslationSearchResult
     */
    protected $result;

    public function __construct(CategoryTranslationSearchResult $result)
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
