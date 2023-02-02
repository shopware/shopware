<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Order\Aggregate\OrderTransactionCapture;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\TestBuilderTrait;

/**
 * @internal
 */
#[Package('customer-order')]
class OrderTransactionCaptureBuilder
{
    use BasicTestDataBehaviour;
    use KernelTestBehaviour;
    use TestBuilderTrait;

    protected string $id;

    protected CalculatedPrice $amount;

    protected string $stateId;

    protected array $refunds = [];

    public function __construct(
        IdsCollection $ids,
        string $key,
        protected string $orderTransactionId,
        float $amount = 420.69,
        string $state = OrderTransactionCaptureStates::STATE_PENDING,
        protected ?string $externalReference = null
    ) {
        $this->id = $ids->get($key);
        $this->ids = $ids;
        $this->stateId = $this->getStateMachineState(OrderTransactionCaptureStates::STATE_MACHINE, $state);

        $this->amount($amount);
    }

    public function amount(float $amount): self
    {
        $this->amount = new CalculatedPrice($amount, $amount, new CalculatedTaxCollection(), new TaxRuleCollection());

        return $this;
    }

    public function addRefund(string $key, array $customParams = []): self
    {
        $refund = \array_replace([
            'id' => $this->ids->get($key),
            'captureId' => $this->id,
            'stateId' => $this->getStateMachineState(
                OrderTransactionCaptureRefundStates::STATE_MACHINE,
                OrderTransactionCaptureRefundStates::STATE_OPEN
            ),
            'externalReference' => null,
            'reason' => null,
            'amount' => new CalculatedPrice(
                420.69,
                420.69,
                new CalculatedTaxCollection(),
                new TaxRuleCollection()
            ),
        ], $customParams);

        $this->refunds[$this->ids->get($key)] = $refund;

        return $this;
    }
}
