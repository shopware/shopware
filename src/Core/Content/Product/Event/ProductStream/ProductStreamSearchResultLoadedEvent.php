<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductStream;

use Shopware\Content\Product\Struct\ProductStreamSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ProductStreamSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'product_stream.search.result.loaded';

    /**
     * @var ProductStreamSearchResult
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
