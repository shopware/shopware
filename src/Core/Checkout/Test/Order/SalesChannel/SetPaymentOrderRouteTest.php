<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Order\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\Event\OrderPaymentMethodChangedEvent;
use Shopware\Core\Checkout\Test\Customer\SalesChannel\CustomerTestTrait;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class SetPaymentOrderRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestTrait;
    use MailTemplateTestBehaviour;

    private KernelBrowser $browser;

    private EntityRepositoryInterface $customerRepository;

    private IdsCollection $ids;

    private ?OrderPaymentMethodChangedEvent $paymentMethodChangedEventResult;

    private ?StateMachineTransitionEvent $transactionStateEventResult;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var EntityRepositoryInterface $customerRepository */
        $customerRepository = $this->getContainer()->get('customer.repository');
        $this->customerRepository = $customerRepository;
        $this->ids = new TestDataCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'paymentMethods' => [
                ['id' => $this->getAvailablePaymentMethodId()],
                ['id' => $this->getAvailablePaymentMethodId(1)],
            ],
        ]);

        $this->assignSalesChannelContext($this->browser);

        $email = Uuid::randomHex() . '@example.com';
        $customerId = $this->createCustomer('shopware', $email);

        $this->ids->set('order-1', $this->createOrder($customerId));
        $this->ids->set('order-2', $this->createOrder($this->createCustomer('test', 'test-other@test.de')));

        $this->browser->request(
            'POST',
            '/store-api/account/login',
            [
                'email' => $email,
                'password' => 'shopware',
            ]
        );
        $response = json_decode($this->browser->getResponse()->getContent(), true);
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $response['contextToken']);

        $this->paymentMethodChangedEventResult = null;
        $this->catchEvent(OrderPaymentMethodChangedEvent::class, $this->paymentMethodChangedEventResult);
        $this->transactionStateEventResult = null;
        $this->catchEvent(StateMachineTransitionEvent::class, $this->transactionStateEventResult);
    }

    public function testSetPaymentMethodOwnOrderOtherPaymentMethodOpen(): void
    {
        $this->sendValidRequest($this->ids->get('order-1'), $this->getAvailablePaymentMethodId(1));
        $transactions = $this->getTransactions($this->ids->get('order-1'));
        static::assertCount(2, $transactions);
        $firstTransaction = $transactions->first();
        static::assertNotNull($firstTransaction);
        $lastTransaction = $transactions->last();
        static::assertNotNull($lastTransaction);
        static::assertNotSame($firstTransaction->getId(), $lastTransaction->getId());

        static::assertSame('cancelled', $firstTransaction->getStateMachineState()->getTechnicalName());
        static::assertSame('open', $lastTransaction->getStateMachineState()->getTechnicalName());

        static::assertNotNull($this->paymentMethodChangedEventResult);
        static::assertSame($lastTransaction->getId(), $this->paymentMethodChangedEventResult->getOrderTransaction()->getId());
        static::assertNotNull($this->transactionStateEventResult);
        static::assertSame($firstTransaction->getId(), $this->transactionStateEventResult->getEntityId());
        static::assertSame('open', $this->transactionStateEventResult->getFromPlace()->getTechnicalName());
        static::assertSame('cancelled', $this->transactionStateEventResult->getToPlace()->getTechnicalName());
    }

    public function testSetPaymentMethodOwnOrderOtherPaymentMethodCancelled(): void
    {
        $this->setFirstTransactionState($this->ids->get('order-1'));
        $this->sendValidRequest($this->ids->get('order-1'), $this->getAvailablePaymentMethodId(1));
        $transactions = $this->getTransactions($this->ids->get('order-1'));
        static::assertCount(2, $transactions);
        $firstTransaction = $transactions->first();
        static::assertNotNull($firstTransaction);
        $lastTransaction = $transactions->last();
        static::assertNotNull($lastTransaction);
        static::assertNotSame($firstTransaction->getId(), $lastTransaction->getId());

        static::assertSame('cancelled', $firstTransaction->getStateMachineState()->getTechnicalName());
        static::assertSame('open', $lastTransaction->getStateMachineState()->getTechnicalName());

        static::assertNotNull($this->paymentMethodChangedEventResult);
        static::assertSame($lastTransaction->getId(), $this->paymentMethodChangedEventResult->getOrderTransaction()->getId());
        static::assertNull($this->transactionStateEventResult);
    }

    public function testSetPaymentMethodOwnOrderWithSamePaymentMethodOpen(): void
    {
        $this->sendValidRequest($this->ids->get('order-1'), $this->getAvailablePaymentMethodId());

        $transactions = $this->getTransactions($this->ids->get('order-1'));
        static::assertCount(1, $transactions);
        $lastTransaction = $transactions->last();
        static::assertNotNull($lastTransaction);

        static::assertSame('open', $lastTransaction->getStateMachineState()->getTechnicalName());
        static::assertNull($this->paymentMethodChangedEventResult);
        static::assertNull($this->transactionStateEventResult);
    }

    public function testSetPaymentMethodOwnOrderWithSamePaymentMethodCancelled(): void
    {
        $this->setFirstTransactionState($this->ids->get('order-1'));
        $this->sendValidRequest($this->ids->get('order-1'), $this->getAvailablePaymentMethodId());

        $transactions = $this->getTransactions($this->ids->get('order-1'));
        static::assertCount(1, $transactions);
        $lastTransaction = $transactions->last();
        static::assertNotNull($lastTransaction);

        static::assertSame('open', $lastTransaction->getStateMachineState()->getTechnicalName());
        static::assertNull($this->paymentMethodChangedEventResult);
        static::assertNotNull($this->transactionStateEventResult);
        static::assertSame($lastTransaction->getId(), $this->transactionStateEventResult->getEntityId());
        static::assertSame('cancelled', $this->transactionStateEventResult->getFromPlace()->getTechnicalName());
        static::assertSame('open', $this->transactionStateEventResult->getToPlace()->getTechnicalName());
    }

    public function testSetPaymentMethodOwnOrderWithSamePaymentMethodInNotMostRecentTransaction(): void
    {
        $this->sendValidRequest($this->ids->get('order-1'), $this->getAvailablePaymentMethodId(1));
        $this->setFirstTransactionState($this->ids->get('order-1'), OrderTransactionStates::STATE_OPEN);
        $this->sendValidRequest($this->ids->get('order-1'), $this->getAvailablePaymentMethodId());

        $transactions = $this->getTransactions($this->ids->get('order-1'));
        static::assertCount(3, $transactions);
        $firstTransaction = $transactions->first();
        static::assertNotNull($firstTransaction);
        $lastTransaction = $transactions->last();
        static::assertNotNull($lastTransaction);

        static::assertSame('open', $lastTransaction->getStateMachineState()->getTechnicalName());
        static::assertNotNull($this->paymentMethodChangedEventResult);
        static::assertSame($lastTransaction->getId(), $this->paymentMethodChangedEventResult->getOrderTransaction()->getId());
        static::assertNotNull($this->transactionStateEventResult);
        static::assertNotSame($firstTransaction->getId(), $this->transactionStateEventResult->getEntityId());
        static::assertNotSame($lastTransaction->getId(), $this->transactionStateEventResult->getEntityId());
        static::assertSame('open', $this->transactionStateEventResult->getFromPlace()->getTechnicalName());
        static::assertSame('cancelled', $this->transactionStateEventResult->getToPlace()->getTechnicalName());
    }

    public function testSetPaymentMethodRandomOrder(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/order/payment',
                [
                    'orderId' => Uuid::randomHex(),
                    'paymentMethodId' => $this->getAvailablePaymentMethodId(1),
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(Response::HTTP_NOT_FOUND, $this->browser->getResponse()->getStatusCode());
        static::assertSame('FRAMEWORK__ENTITY_NOT_FOUND', $response['errors'][0]['code']);
        static::assertNull($this->paymentMethodChangedEventResult);
        static::assertNull($this->transactionStateEventResult);
    }

    public function testSetPaymentMethodOtherUsersOrder(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/order/payment',
                [
                    'orderId' => $this->ids->get('order-2'),
                    'paymentMethodId' => $this->getAvailablePaymentMethodId(1),
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(Response::HTTP_NOT_FOUND, $this->browser->getResponse()->getStatusCode());
        static::assertSame('FRAMEWORK__ENTITY_NOT_FOUND', $response['errors'][0]['code']);
        static::assertNull($this->paymentMethodChangedEventResult);
        static::assertNull($this->transactionStateEventResult);
    }

    public function testSetPaymentMethodWithoutLogin(): void
    {
        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => Uuid::randomHex(),
        ]);

        $this->browser
            ->request(
                'POST',
                '/store-api/order/payment',
                [
                    'orderId' => $this->ids->get('order-2'),
                    'paymentMethodId' => $this->getAvailablePaymentMethodId(1),
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(Response::HTTP_FORBIDDEN, $this->browser->getResponse()->getStatusCode());
        static::assertSame('CHECKOUT__CUSTOMER_NOT_LOGGED_IN', $response['errors'][0]['code']);
        static::assertNull($this->paymentMethodChangedEventResult);
        static::assertNull($this->transactionStateEventResult);
    }

    private function createOrder(string $customerId): string
    {
        $id = Uuid::randomHex();

        $this->getContainer()->get('order.repository')->create(
            [[
                'id' => $id,
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
                'orderNumber' => 'anOrderNumber',
                'stateId' => $this->getStateMachineState(),
                'currencyId' => Defaults::CURRENCY,
                'currencyFactor' => 1.0,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'billingAddressId' => $billingAddressId = Uuid::randomHex(),
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
                'lineItems' => [
                    [
                        'id' => $this->ids->get('VoucherA'),
                        'type' => LineItem::PROMOTION_LINE_ITEM_TYPE,
                        'code' => $this->ids->get('VoucherA'),
                        'identifier' => $this->ids->get('VoucherA'),
                        'quantity' => 1,
                        'payload' => ['promotionId' => $this->ids->get('voucherA')],
                        'label' => 'label',
                        'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                    ],
                ],
                'deliveries' => [],
                'transactions' => [
                    [
                        'paymentMethodId' => $this->getAvailablePaymentMethodId(),
                        'stateId' => $this->getStateMachineState(OrderTransactionStates::STATE_MACHINE, OrderTransactionStates::STATE_OPEN),
                        'amount' => new CalculatedPrice(10.0, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    ],
                ],
                'context' => '{}',
                'payload' => '{}',
            ]],
            Context::createDefaultContext()
        );

        return $id;
    }

    private function sendValidRequest(string $orderId, string $paymentMethodId): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/order/payment',
                [
                    'orderId' => $orderId,
                    'paymentMethodId' => $paymentMethodId,
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_OK, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($response['success']);
    }

    private function getAvailablePaymentMethodId($offset = 0): string
    {
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())
            ->setLimit(1)
            ->setOffset($offset)
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('availabilityRuleId', null));

        $id = $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertNotNull($id);

        return $id;
    }

    private function getTransactions(string $orderId): OrderTransactionCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addSorting(new FieldSorting('createdAt'));

        /** @var OrderTransactionCollection $transactions */
        $transactions = $this->getContainer()->get('order_transaction.repository')->search($criteria, Context::createDefaultContext())->getEntities();

        return $transactions;
    }

    private function setFirstTransactionState(string $orderId, string $state = OrderTransactionStates::STATE_CANCELLED): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));
        $criteria->addSorting(new FieldSorting('createdAt'));

        $transactionId = $this->getContainer()->get('order_transaction.repository')->searchIds($criteria, Context::createDefaultContext())->firstId();
        $this->getContainer()->get('order_transaction.repository')->update([[
            'id' => $transactionId,
            'stateId' => $this->getStateMachineState(OrderTransactionStates::STATE_MACHINE, $state),
        ]], Context::createDefaultContext());
    }
}
