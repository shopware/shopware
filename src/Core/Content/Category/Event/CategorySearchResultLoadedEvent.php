<?php declare(strict_types=1);

namespace Shopware\Content\Category\Event;

use Shopware\Framework\Context;
use Shopware\Content\Category\Struct\CategorySearchResult;
use Shopware\Framework\Event\NestedEvent;

class CategorySearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'category.search.result.loaded';

    /**
     * @var CategorySearchResult
     */
    protected $result;

    public function __construct(CategorySearchResult $result)
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
