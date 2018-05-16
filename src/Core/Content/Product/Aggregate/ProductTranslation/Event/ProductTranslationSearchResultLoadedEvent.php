<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductTranslation\Event;

use Shopware\Content\Product\Aggregate\ProductTranslation\Struct\ProductTranslationSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
