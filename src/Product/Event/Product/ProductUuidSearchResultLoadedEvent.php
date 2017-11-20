<?php declare(strict_types=1);

namespace Shopware\Product\Event\Product;

use Shopware\Api\Search\UuidSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class ProductUuidSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'product.uuid.search.result.loaded';

    /**
     * @var UuidSearchResult
     */
    protected $result;

    public function __construct(UuidSearchResult $result)
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

    public function getResult(): UuidSearchResult
    {
        return $this->result;
    }
}
