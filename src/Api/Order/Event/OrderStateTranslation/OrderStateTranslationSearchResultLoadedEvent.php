<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderStateTranslation;

use Shopware\Api\Order\Struct\OrderStateTranslationSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class OrderStateTranslationSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'order_state_translation.search.result.loaded';

    /**
     * @var OrderStateTranslationSearchResult
     */
    protected $result;

    public function __construct(OrderStateTranslationSearchResult $result)
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
