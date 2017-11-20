<?php declare(strict_types=1);

namespace Shopware\Order\Event\OrderDelivery;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Order\Struct\OrderDeliverySearchResult;

class OrderDeliverySearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'order_delivery.search.result.loaded';

    /**
     * @var OrderDeliverySearchResult
     */
    protected $result;

    public function __construct(OrderDeliverySearchResult $result)
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
