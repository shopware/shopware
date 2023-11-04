<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Order\Aggregate\OrderTransactionCaptureRefund;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\TestBuilderTrait;

/**
 * @internal
 */
#[Package('customer-order')]
class OrderTransactionCaptureRefundBuilder
{
    use BasicTestDataBehaviour;
    use KernelTestBehaviour;
    use TestBuilderTrait;

    protected string $id;

    protected CalculatedPrice $amount;

    protected string $stateId;

    protected array $positions = [];

    public function __construct(
        IdsCollection $ids,
        string $key,
        protected string $captureId,
        float $amount = 420.69,
        string $state = OrderTransactionCaptureRefundStates::STATE_OPEN,
        protected ?string $externalReference = null,
        protected ?string $reason = null
    ) {
        $this->id = $ids->get($key);
        $this->ids = $ids;
        $this->stateId = $this->getStateMachineState(
            OrderTransactionCaptureRefundStates::STATE_MACHINE,
            $state
        );

        $this->amount($amount);
    }

    public function amount(float $amount): self
    {
        $this->amount = new CalculatedPrice($amount, $amount, new CalculatedTaxCollection(), new TaxRuleCollection());

        return $this;
    }

    public function addPosition(string $key, array $customParams = []): self
    {
        if (\array_key_exists('amount', $customParams)) {
            if (\is_float($customParams['amount'])) {
                $customParams['amount'] = new CalculatedPrice(
                    $customParams['amount'],
                    $customParams['amount'],
                    new CalculatedTaxCollection(),
                    new TaxRuleCollection()
                );
            }
        }

        $lineItem = [];

        // if orderLineItem is not submitted, create a orderLineItem on the fly
        if (!\array_key_exists('orderLineItem', $customParams)) {
            $lineItem = ['orderLineItem' => (new ProductBuilder($this->ids, '10000'))->build()];
            $lineItem['orderLineItem']['identifier'] = $this->ids->get('10000');
            $lineItem['orderLineItem']['quantity'] = 1;
            $lineItem['orderLineItem']['label'] = 'foo';
            $lineItem['orderLineItem']['price'] = new CalculatedPrice(
                420.69,
                420.69,
                new CalculatedTaxCollection(),
                new TaxRuleCollection()
            );
        }

        $position = \array_replace([
            'refundId' => $this->id,
            'orderLineItemId' => \array_key_exists('orderLineItem', $customParams)
                ? $customParams['orderLineItem']
                : $lineItem['orderLineItem']['id'],
            'quantity' => 1,
            'reason' => null,
            'refundPrice' => 420.69,
            'amount' => new CalculatedPrice(
                420.69,
                420.69,
                new CalculatedTaxCollection(),
                new TaxRuleCollection()
            ),
            'externalReference' => null,
        ], $customParams, $lineItem);

        $this->positions[$this->ids->get($key)] = $position;

        return $this;
    }
}
