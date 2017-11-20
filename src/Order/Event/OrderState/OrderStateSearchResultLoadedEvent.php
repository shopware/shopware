<?php declare(strict_types=1);

namespace Shopware\Order\Event\OrderState;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Order\Struct\OrderStateSearchResult;

class OrderStateSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'order_state.search.result.loaded';

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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
