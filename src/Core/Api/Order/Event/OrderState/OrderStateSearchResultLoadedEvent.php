<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderState;

use Shopware\Api\Order\Struct\OrderStateSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class OrderStateSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'order_state.search.result.loaded';

    /**
     * @var OrderStateSearchResult
     */
    protected $result;

    public function __construct(OrderStateSearchResult $result)
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
