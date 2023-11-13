<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Payload\Struct;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Cart\Recurring\RecurringDataStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\CloneTrait;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class AsyncFinalizePayload implements PaymentPayloadInterface
{
    use CloneTrait;
    use JsonSerializableTrait;
    use RemoveAppTrait;

    protected Source $source;

    protected OrderTransactionEntity $orderTransaction;

    /**
     * @param mixed[] $queryParameters
     */
    public function __construct(
        OrderTransactionEntity $orderTransaction,
        protected array $queryParameters,
        protected ?RecurringDataStruct $recurring = null,
    ) {
        $this->orderTransaction = $this->removeApp($orderTransaction);
    }

    public function setSource(Source $source): void
    {
        $this->source = $source;
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    public function getOrderTransaction(): OrderTransactionEntity
    {
        return $this->orderTransaction;
    }

    /**
     * @return mixed[]
     */
    public function getQueryParameters(): array
    {
        return $this->queryParameters;
    }

    public function getRecurring(): ?RecurringDataStruct
    {
        return $this->recurring;
    }
}
