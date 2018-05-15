<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductContextPrice;

use Shopware\Framework\ORM\Search\IdSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ProductContextPriceIdSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'product_context_price.id.search.result.loaded';

    /**
     * @var IdSearchResult
     */
    protected $result;

    public function __construct(IdSearchResult $result)
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

    public function getResult(): IdSearchResult
    {
        return $this->result;
    }
}
