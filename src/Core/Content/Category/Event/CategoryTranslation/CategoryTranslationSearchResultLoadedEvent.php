<?php declare(strict_types=1);

namespace Shopware\Content\Category\Event\CategoryTranslation;

use Shopware\Content\Category\Struct\CategoryTranslationSearchResult;
use Shopware\Context\Struct\ApplicationContext;
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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
