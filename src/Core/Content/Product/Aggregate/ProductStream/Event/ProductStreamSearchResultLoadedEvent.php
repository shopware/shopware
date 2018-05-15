<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductStream\Event;

use Shopware\Content\Product\Aggregate\ProductStream\Struct\ProductStreamSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ProductStreamSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'product_stream.search.result.loaded';

    /**
     * @var \Shopware\Content\Product\Aggregate\ProductStream\Struct\ProductStreamSearchResult
     */
    protected $result;

    public function __construct(ProductStreamSearchResult $result)
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
