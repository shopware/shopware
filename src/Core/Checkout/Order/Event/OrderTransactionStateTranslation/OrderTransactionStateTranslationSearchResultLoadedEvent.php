<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Event\OrderTransactionStateTranslation;

use Shopware\Checkout\Order\Struct\OrderTransactionStateTranslationSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class OrderTransactionStateTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'order_transaction_state_translation.search.result.loaded';

    /**
     * @var OrderTransactionStateTranslationSearchResult
     */
    protected $result;

    public function __construct(OrderTransactionStateTranslationSearchResult $result)
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
