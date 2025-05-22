<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Order\Aggregate\OrderTransaction;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class OrderTransactionStateHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    /**
     * @var EntityRepository<OrderTransactionCollection>
     */
    private EntityRepository $orderTransactionRepository;

    private OrderTransactionStateHandler $orderTransactionStateHelper;

    private Context $context;

    private string $transactionId;

    protected function setUp(): void
    {
        $this->customerRepository = static::getContainer()->get('customer.repository');
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->orderTransactionRepository = static::getContainer()->get('order_transaction.repository');
        $this->orderTransactionStateHelper = static::getContainer()->get(OrderTransactionStateHandler::class);
        $this->context = Context::createDefaultContext();
        $this->transactionId = $this->createOrderTransaction($this->createOrder($this->createCustomer()));
    }

    public function testCancel(): void
    {
        $this->orderTransactionStateHelper->cancel($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_CANCELLED, $this->retrieveTransaction());
    }

    public function testAsyncProcessAndPay(): void
    {
        $this->orderTransactionStateHelper->processUnconfirmed($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_UNCONFIRMED, $this->retrieveTransaction());

        $this->orderTransactionStateHelper->paid($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_PAID, $this->retrieveTransaction());
    }

    public function testProcessAndPay(): void
    {
        $this->orderTransactionStateHelper->process($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_IN_PROGRESS, $this->retrieveTransaction());

        $this->orderTransactionStateHelper->paid($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_PAID, $this->retrieveTransaction());
    }

    public function testCancelAndReopen(): void
    {
        $this->orderTransactionStateHelper->cancel($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_CANCELLED, $this->retrieveTransaction());

        $this->orderTransactionStateHelper->reopen($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_OPEN, $this->retrieveTransaction());
    }

    public function testPayAndRefund(): void
    {
        $this->orderTransactionStateHelper->paid($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_PAID, $this->retrieveTransaction());

        $this->orderTransactionStateHelper->refund($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_REFUNDED, $this->retrieveTransaction());
    }

    public function testPartiallyPayAndRefund(): void
    {
        $this->orderTransactionStateHelper->paidPartially($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_PARTIALLY_PAID, $this->retrieveTransaction());

        $this->orderTransactionStateHelper->refund($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_REFUNDED, $this->retrieveTransaction());
    }

    public function testPayAndPartiallyRefund(): void
    {
        $this->orderTransactionStateHelper->paid($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_PAID, $this->retrieveTransaction());

        $this->orderTransactionStateHelper->refund($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_REFUNDED, $this->retrieveTransaction());
    }

    public function testRemindAndProcessAndFail(): void
    {
        $this->orderTransactionStateHelper->remind($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_REMINDED, $this->retrieveTransaction());

        $this->orderTransactionStateHelper->process($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_IN_PROGRESS, $this->retrieveTransaction());

        $this->orderTransactionStateHelper->fail($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_FAILED, $this->retrieveTransaction());
    }

    public function testPartiallyPayAndProcessUnconfirmedAndPay(): void
    {
        $this->orderTransactionStateHelper->paidPartially($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_PARTIALLY_PAID, $this->retrieveTransaction());

        $this->orderTransactionStateHelper->processUnconfirmed($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_UNCONFIRMED, $this->retrieveTransaction());

        $this->orderTransactionStateHelper->paid($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_PAID, $this->retrieveTransaction());
    }

    public function testPayAndChargebackAndCancel(): void
    {
        $this->orderTransactionStateHelper->paid($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_PAID, $this->retrieveTransaction());

        $this->orderTransactionStateHelper->chargeback($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_CHARGEBACK, $this->retrieveTransaction());

        $this->orderTransactionStateHelper->cancel($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_CANCELLED, $this->retrieveTransaction());
    }

    public function testPartiallyPayAndPay(): void
    {
        $this->orderTransactionStateHelper->paidPartially($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_PARTIALLY_PAID, $this->retrieveTransaction());

        $this->orderTransactionStateHelper->paid($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_PAID, $this->retrieveTransaction());
    }

    public function testRemindAndPay(): void
    {
        $this->orderTransactionStateHelper->remind($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_REMINDED, $this->retrieveTransaction());

        $this->orderTransactionStateHelper->paid($this->transactionId, $this->context);
        static::assertSame(OrderTransactionStates::STATE_PAID, $this->retrieveTransaction());
    }

    private function createOrder(string $customerId): string
    {
        $orderId = Uuid::randomHex();
        $stateId = static::getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE);
        $billingAddressId = Uuid::randomHex();

        $order = [
            'id' => $orderId,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderNumber' => Uuid::randomHex(),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderCustomer' => [
                'customerId' => $customerId,
                'email' => 'test@example.com',
                'salutationId' => $this->getValidSalutationId(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ],
            'stateId' => $stateId,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'billingAddressId' => $billingAddressId,
            'addresses' => [
                [
                    'id' => $billingAddressId,
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                    'countryId' => $this->getValidCountryId(),
                ],
            ],
            'lineItems' => [],
            'deliveries' => [
            ],
            'context' => '{}',
            'payload' => '{}',
        ];

        $this->orderRepository->upsert([$order], $this->context);

        return $orderId;
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'customerNumber' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->customerRepository->upsert([$customer], $this->context);

        return $customerId;
    }

    private function createOrderTransaction(string $orderId): string
    {
        $transactionId = Uuid::randomHex();
        $stateId = static::getContainer()->get(InitialStateIdLoader::class)->get(OrderTransactionStates::STATE_MACHINE);

        $transaction = [
            'id' => $transactionId,
            'orderId' => $orderId,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'stateId' => $stateId,
            'amount' => new CalculatedPrice(
                100,
                100,
                new CalculatedTaxCollection(),
                new TaxRuleCollection()
            ),
        ];

        $this->orderTransactionRepository->upsert([$transaction], $this->context);

        return $transactionId;
    }

    private function retrieveTransaction(): ?string
    {
        $criteria = new Criteria([$this->transactionId]);
        $criteria->addAssociation('stateMachineState');

        /** @var OrderTransactionEntity|null $transaction */
        $transaction = $this->orderTransactionRepository->search($criteria, $this->context)->first();

        return $transaction?->getStateMachineState()?->getTechnicalName();
    }
}
