<?php declare(strict_types=1);

namespace Shopware\Api\Order\Event\OrderTransactionStateTranslation;

use Shopware\Api\Entity\Search\AggregationResult;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

class OrderTransactionStateTranslationAggregationResultLoadedEvent extends NestedEvent
{
    public const NAME = 'order_transaction_state_translation.aggregation.result.loaded';

    /**
     * @var AggregationResult
     */
    protected $result;

    public function __construct(AggregationResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->result->getContext();
    }

    public function getResult(): AggregationResult
    {
        return $this->result;
    }
}
