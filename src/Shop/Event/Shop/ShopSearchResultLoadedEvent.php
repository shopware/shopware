<?php declare(strict_types=1);

namespace Shopware\Shop\Event\Shop;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Shop\Struct\ShopSearchResult;

class ShopSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'shop.search.result.loaded';

    /**
     * @var ShopSearchResult
     */
    protected $result;

    public function __construct(ShopSearchResult $result)
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
