<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Category\Struct\CategorySearchResult;
use Shopware\Core\Framework\Event\NestedEvent;

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
