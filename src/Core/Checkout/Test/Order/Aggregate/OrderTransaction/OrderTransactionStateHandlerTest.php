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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('customer-order')]
class OrderTransactionStateHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $customerRepository;

    private EntityRepository $orderRepository;

    private EntityRepository $orderTransactionRepository;

    private StateMachineRegistry $stateMachineRegistry;

    private OrderTransactionStateHandler $orderTransactionStateHelper;

    protected function setUp(): void
    {
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->orderTransactionRepository = $this->getContainer()->get('order_transaction.repository');
        $this->stateMachineRegistry = $this->getContainer()->get(StateMachineRegistry::class);
        $this->orderTransactionStateHelper = $this->getContainer()->get(OrderTransactionStateHandler::class);
    }

    public static function dataProviderActions(): array
    {
        return [
            'Cancel' => [[
                'cancel' => OrderTransactionStates::STATE_CANCELLED,
            ]],
            'Async Process & Pay' => [[
                'processUnconfirmed' => OrderTransactionStates::STATE_UNCONFIRMED,
                'paid' => OrderTransactionStates::STATE_PAID,
            ]],
            'Process & Pay' => [[
                'process' => OrderTransactionStates::STATE_IN_PROGRESS,
                'paid' => OrderTransactionStates::STATE_PAID,
            ]],
            'Cancel & Reopen' => [[
                'cancel' => OrderTransactionStates::STATE_CANCELLED,
                'reopen' => OrderTransactionStates::STATE_OPEN,
            ]],
            'Pay & Refund' => [[
                'paid' => OrderTransactionStates::STATE_PAID,
                'refund' => OrderTransactionStates::STATE_REFUNDED,
            ]],
            'Partially pay & Refund' => [[
                'payPartially' => OrderTransactionStates::STATE_PARTIALLY_PAID,
                'refund' => OrderTransactionStates::STATE_REFUNDED,
            ]],
            'Pay & Partially Refund' => [[
                'paid' => OrderTransactionStates::STATE_PAID,
                'refundPartially' => OrderTransactionStates::STATE_PARTIALLY_REFUNDED,
            ]],
            'Remind & Process & Fail' => [[
                'remind' => OrderTransactionStates::STATE_REMINDED,
                'process' => OrderTransactionStates::STATE_IN_PROGRESS,
                'fail' => OrderTransactionStates::STATE_FAILED,
            ]],
            'Partially Pay & Process & Pay' => [[
                'payPartially' => OrderTransactionStates::STATE_PARTIALLY_PAID,
                'processUnconfirmed' => OrderTransactionStates::STATE_UNCONFIRMED,
                'paid' => OrderTransactionStates::STATE_PAID,
            ]],
            'Pay & Chargeback & Cancel' => [[
                'paid' => OrderTransactionStates::STATE_PAID,
                'chargeback' => OrderTransactionStates::STATE_CHARGEBACK,
                'cancel' => OrderTransactionStates::STATE_CANCELLED,
            ]],
        ];
    }

    /**
     * @dataProvider dataProviderActions
     *
     * @param array<string, string>
     */
    public function testAction(array $path): void
    {
        $context = Context::createDefaultContext();
        $customerId = $this->createCustomer($context);
        $orderId = $this->createOrder($customerId, $context);
        $transactionId = $this->createOrderTransaction($orderId, $context);

        foreach ($path as $action => $destinationState) {
            $this->orderTransactionStateHelper->$action($transactionId, $context); /* @phpstan-ignore-line */

            $criteria = new Criteria([$transactionId]);
            $criteria->addAssociation('stateMachineState');
            $transaction = $this->orderTransactionRepository->search($criteria, $context)->first();

            static::assertSame($destinationState, $transaction->getStateMachineState()->getTechnicalName());
        }
    }

    private function createOrder(string $customerId, Context $context): string
    {
        $orderId = Uuid::randomHex();
        $stateId = $this->getContainer()->get(InitialStateIdLoader::class)->get(OrderStates::STATE_MACHINE);
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

        $this->customerRepository->upsert([$customer], $context);

        return $customerId;
    }

    private function createOrderTransaction(string $orderId, Context $context): string
    {
        $transactionId = Uuid::randomHex();
        $stateId = $this->getContainer()->get(InitialStateIdLoader::class)->get(OrderTransactionStates::STATE_MACHINE);

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
