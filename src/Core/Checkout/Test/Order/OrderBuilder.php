<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Order;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Test\TestBuilderTrait;
use Shopware\Core\Test\TestDefaults;

/**
 * @package customer-order
 *
 * @internal
 */
class OrderBuilder
{
    use BasicTestDataBehaviour;
    use KernelTestBehaviour;
    use TestBuilderTrait;

    protected string $id;

    protected string $orderNumber;

    protected string $salesChannelId;

    protected string $currencyId;

    protected float $currencyFactor;

    protected string $billingAddressId;

    protected string $orderDateTime;

    protected CartPrice $price;

    protected CalculatedPrice $shippingCosts;

    protected array $lineItems = [];

    protected array $transactions = [];

    protected array $addresses = [];

    protected string $stateId;

    public function __construct(
        IdsCollection $ids,
        string $orderNumber,
        string $salesChannelId = TestDefaults::SALES_CHANNEL
    ) {
        $this->ids = $ids;
        $this->id = $ids->get($orderNumber);
        $this->billingAddressId = $ids->get('billing_address');
        $this->currencyId = Defaults::CURRENCY;
        $this->stateId = $this->getStateMachineState();
        $this->orderNumber = $orderNumber;
        $this->salesChannelId = $salesChannelId;
        $this->orderDateTime = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->currencyFactor = 1.0;

        $this->price(420.69);
        $this->shippingCosts(0);
    }

    public function price(float $amount): self
    {
        $this->price = new CartPrice(
            $amount,
            $amount,
            $amount,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_FREE
        );

        return $this;
    }

    public function shippingCosts(float $amount): self
    {
        $this->shippingCosts = new CalculatedPrice(
            $amount,
            $amount,
            new CalculatedTaxCollection(),
            new TaxRuleCollection()
        );

        return $this;
    }

    public function addTransaction(string $key, array $customParams = []): self
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

        $transaction = \array_replace([
            'id' => $this->ids->get($key),
            'orderId' => $this->id,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'amount' => new CalculatedPrice(
                420.69,
                420.69,
                new CalculatedTaxCollection(),
                new TaxRuleCollection()
            ),
            'stateId' => $this->getStateMachineState(
                OrderTransactionStates::STATE_MACHINE,
                OrderTransactionStates::STATE_OPEN
            ),
        ], $customParams);

        $this->transactions[$this->ids->get($key)] = $transaction;

        return $this;
    }

    public function addAddress(string $key, array $customParams = []): self
    {
        $address = \array_replace([
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'city' => 'Bielefeld',
            'street' => 'Buchenweg 5',
            'zipcode' => '33062',
            'country' => [
                'id' => $this->ids->get($key),
                'name' => 'Germany',
            ],
        ], $customParams);

        $this->addresses[$key] = $address;

        return $this;
    }
}
