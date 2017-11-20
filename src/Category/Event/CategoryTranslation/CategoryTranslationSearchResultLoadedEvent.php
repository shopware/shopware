<?php declare(strict_types=1);

namespace Shopware\Category\Event\CategoryTranslation;

use Shopware\Category\Struct\CategoryTranslationSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class CategoryTranslationSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'category_translation.search.result.loaded';

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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
