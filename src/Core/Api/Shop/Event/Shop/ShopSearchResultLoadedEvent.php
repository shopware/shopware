<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\Shop;

use Shopware\Api\Shop\Struct\ShopSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ShopSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'shop.search.result.loaded';

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
