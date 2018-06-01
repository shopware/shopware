<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\Struct\ProductTranslationSearchResult;
use Shopware\Core\Framework\Event\NestedEvent;

class ProductTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'product_translation.search.result.loaded';

    /**
     * @var ProductTranslationSearchResult
     */
    protected $result;

    public function __construct(ProductTranslationSearchResult $result)
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
