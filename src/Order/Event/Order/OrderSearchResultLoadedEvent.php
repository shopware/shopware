<?php declare(strict_types=1);

namespace Shopware\Order\Event\Order;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Order\Struct\OrderSearchResult;

class OrderSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'order.search.result.loaded';

    /**
     * @var OrderSearchResult
     */
    protected $result;

    public function __construct(OrderSearchResult $result)
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
