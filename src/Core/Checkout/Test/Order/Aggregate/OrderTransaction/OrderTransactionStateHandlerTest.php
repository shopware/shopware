<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Order\Aggregate\OrderTransaction;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\StateMachineRegistry;

class OrderTransactionStateHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderTransactionRepository;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    /**
     * @var OrderTransactionStateHandler
     */
    private $orderTransactionStateHelper;

    public function setUp(): void
    {
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->orderTransactionRepository = $this->getContainer()->get('order_transaction.repository');
        $this->stateMachineRegistry = $this->getContainer()->get(StateMachineRegistry::class);
        $this->orderTransactionStateHelper = $this->getContainer()->get(OrderTransactionStateHandler::class);
    }

    public function testSetCancelState(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);
        $orderId = $this->createOrder($customerId, $context);
        $transactionId = $this->createOrderTransaction($orderId, $context);

        $this->orderTransactionStateHelper->cancel($transactionId, $context);

        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('stateMachineState');
        $transaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        static::assertSame(OrderTransactionStates::STATE_CANCELLED, $transaction->getStateMachineState()->getTechnicalName());
    }

    public function testSetCompleteState(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);
        $orderId = $this->createOrder($customerId, $context);
        $transactionId = $this->createOrderTransaction($orderId, $context);

        $this->orderTransactionStateHelper->process($transactionId, $context);

        $this->orderTransactionStateHelper->paid($transactionId, $context);

        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('stateMachineState');
        $transaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        static::assertSame(OrderTransactionStates::STATE_PAID, $transaction->getStateMachineState()->getTechnicalName());
    }

    public function testSetReopenState(): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);
        $orderId = $this->createOrder($customerId, $context);
        $transactionId = $this->createOrderTransaction($orderId, $context);

        $this->orderTransactionStateHelper->cancel($transactionId, $context);

        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('stateMachineState');
        $transaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        static::assertSame(OrderTransactionStates::STATE_CANCELLED, $transaction->getStateMachineState()->getTechnicalName());

        $this->orderTransactionStateHelper->reopen($transactionId, $context);

        $criteria = new Criteria([$transactionId]);
        $criteria->addAssociation('stateMachineState');
        $transaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        static::assertSame(OrderTransactionStates::STATE_OPEN, $transaction->getStateMachineState()->getTechnicalName());
    }

    private function createOrder(string $customerId, Context $context): string
    {
        $orderId = Uuid::randomHex();
        $stateId = $this->stateMachineRegistry->getInitialState(OrderStates::STATE_MACHINE, $context)->getId();
        $billingAddressId = Uuid::randomHex();

        $order = [
            'id' => $orderId,
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
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'billingAddressId' => $billingAddressId,
            'addresses' => [
                [
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

        $this->orderRepository->upsert([$order], $context);

        return $orderId;
    }

    private function createCustomer(Context $context): string
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
            'password' => 'shopware',
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => Defaults::SALES_CHANNEL,
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

        $this->customerRepository->upsert([$customer], $context);

        return $customerId;
    }

    private function createOrderTransaction(string $orderId, Context $context): string
    {
        $transactionId = Uuid::randomHex();
        $stateId = $this->stateMachineRegistry->getInitialState(OrderTransactionStates::STATE_MACHINE, $context)->getId();

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

        $this->orderTransactionRepository->upsert([$transaction], $context);

        return $transactionId;
    }
}
