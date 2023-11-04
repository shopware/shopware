<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Order\Aggregate\OrderTransaction;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureStates;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\TestBuilderTrait;

/**
 * @internal
 */
#[Package('customer-order')]
class OrderTransactionBuilder
{
    use BasicTestDataBehaviour;
    use KernelTestBehaviour;
    use TestBuilderTrait;

    protected string $id;

    protected string $orderId;

    protected string $paymentMethodId;

    protected CalculatedPrice $amount;

    protected string $stateId;

    protected array $captures = [];

    public function __construct(
        IdsCollection $ids,
        string $key,
        string $orderNumber = '10000',
        float $amount = 420.69,
        string $state = OrderTransactionStates::STATE_OPEN
    ) {
        $this->id = $ids->get($key);
        $this->ids = $ids;
        $this->paymentMethodId = $this->getValidPaymentMethodId();
        $this->orderId = $ids->get($orderNumber);
        $this->stateId = $this->getStateMachineState(OrderTransactionStates::STATE_MACHINE, $state);

        $this->amount($amount);
    }

    public function amount(float $amount): self
    {
        $this->amount = new CalculatedPrice($amount, $amount, new CalculatedTaxCollection(), new TaxRuleCollection());

        return $this;
    }

    public function addCapture(string $key, array $customParams = []): self
    {
        $capture = \array_merge([
            'id' => $this->ids->get($key),
            'orderTransactionId' => $this->id,
            'stateId' => $this->getStateMachineState(
                OrderTransactionCaptureStates::STATE_MACHINE,
                OrderTransactionCaptureStates::STATE_PENDING
            ),
            'externalReference' => null,
            'totalAmount' => 420.69,
            'amount' => new CalculatedPrice(
                420.69,
                420.69,
                new CalculatedTaxCollection(),
                new TaxRuleCollection()
            ),
        ], $customParams);

        $this->captures[$this->ids->get($key)] = $capture;

        return $this;
    }
}
