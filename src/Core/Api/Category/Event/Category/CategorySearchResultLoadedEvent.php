<?php declare(strict_types=1);

namespace Shopware\Api\Category\Event\Category;

use Shopware\Api\Category\Struct\CategorySearchResult;
use Shopware\Context\Struct\ApplicationContext;
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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
