<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\OrderSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Tests\Integration\Core\Checkout\Customer\Rule\OrderFixture;

/**
 * @internal
 */
#[Package('system-settings')]
class OrderSerializerTest extends TestCase
{
    use IntegrationTestBehaviour;

    use OrderFixture;

    private OrderSerializer $serializer;

    private EntityRepository $orderRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $serializerRegistry = $this->getContainer()->get(SerializerRegistry::class);

        $this->serializer = new OrderSerializer();

        $this->serializer->setRegistry($serializerRegistry);

        $this->context = Context::createDefaultContext();
    }

    public function testSerializeOrder(): void
    {
        $order = $this->createOrder();
        static::assertNotNull($order->getBillingAddress());
        static::assertNotNull($order->getOrderCustomer());

        $orderDefinition = $this->getContainer()->get('order.repository')->getDefinition();
        $config = new Config([], [], []);

        $serialized = iterator_to_array($this->serializer->serialize($config, $orderDefinition, $order));

        static::assertNotEmpty($serialized);

        // assert values
        static::assertSame($serialized['id'], $order->getId());
        static::assertSame($serialized['orderNumber'], $order->getOrderNumber());
        static::assertSame($serialized['salesChannelId'], $order->getSalesChannelId());

        static::assertInstanceOf(OrderCustomerEntity::class, $orderCustomer = $serialized['orderCustomer']);
        static::assertSame($orderCustomer->getFirstName(), $order->getOrderCustomer()->getFirstName());
        static::assertSame($orderCustomer->getLastName(), $order->getOrderCustomer()->getLastName());
        static::assertSame($orderCustomer->getEmail(), $order->getOrderCustomer()->getEmail());

        static::assertInstanceOf(OrderAddressEntity::class, $billingAddress = $serialized['billingAddress']);
        static::assertSame($billingAddress->getZipcode(), $order->getBillingAddress()->getZipcode());
        static::assertSame($billingAddress->getStreet(), $order->getBillingAddress()->getStreet());
        static::assertSame($billingAddress->getCity(), $order->getBillingAddress()->getCity());
        static::assertSame($billingAddress->getCompany(), $order->getBillingAddress()->getCompany());
        static::assertSame($billingAddress->getDepartment(), $order->getBillingAddress()->getDepartment());
        static::assertSame($billingAddress->getCountryId(), $order->getBillingAddress()->getCountryId());
        static::assertSame($billingAddress->getCountryStateId(), $order->getBillingAddress()->getCountryStateId());

        static::assertNotNull($deliveries = $order->getDeliveries());
        static::assertNotNull($delivery = $deliveries->first());

        static::assertNotEmpty($serialized['deliveries']);
        static::assertSame($serialized['deliveries']['trackingCodes'], implode('|', $delivery->getTrackingCodes()));
        static::assertSame($serialized['deliveries']['shippingOrderAddress'], $delivery->getShippingOrderAddress());
        static::assertSame($serialized['deliveries']['stateMachineState'], $delivery->getStateMachineState());

        static::assertNotNull($transactions = $order->getTransactions());
        static::assertNotNull($transaction = $transactions->first());

        static::assertSame($serialized['transactions']['_uniqueIdentifier'], $transaction->getUniqueIdentifier());
        static::assertSame($serialized['transactions']['id'], $transaction->getId());
        static::assertSame($serialized['transactions']['versionId'], $transaction->getVersionId());
        static::assertSame($serialized['transactions']['orderId'], $transaction->getOrderId());
        static::assertSame($serialized['transactions']['orderVersionId'], $transaction->getOrderVersionId());
        static::assertSame($serialized['transactions']['paymentMethodId'], $transaction->getPaymentMethodId());
        static::assertSame($serialized['transactions']['amount'], $transaction->getAmount());
        static::assertSame($serialized['transactions']['stateId'], $transaction->getStateId());
        static::assertSame($serialized['transactions']['stateMachineState'], $transaction->getStateMachineState()?->jsonSerialize());

        static::assertNotNull($lineItems = $order->getLineItems());
        static::assertNotNull($lineItem = $lineItems->first());

        static::assertSame($serialized['lineItems'], '1x ' . $lineItem->getProductId());

        static::assertSame($serialized['amountTotal'], $order->getAmountTotal());
        static::assertSame($serialized['stateId'], $order->getStateId());
        static::assertSame($serialized['orderDateTime'], $order->getOrderDateTime()->format('Y-m-d\Th:i:s.vP'));

        static::assertNotNull($itemRounding = $order->getItemRounding());
        static::assertSame($serialized['itemRounding'], $itemRounding->jsonSerialize());

        static::assertNotNull($totalRounding = $order->getTotalRounding());
        static::assertSame($serialized['totalRounding'], $totalRounding->jsonSerialize());
    }

    private function createOrder(): OrderEntity
    {
        // create product
        $productId = Uuid::randomHex();
        $product = $this->getProductData($productId);

        $productRepository = $this->getContainer()->get('product.repository');
        $productRepository->create([$product], $this->context);

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $this->context)[0];

        $orderData['lineItems'][0]['productId'] = $productId;

        $orderData['transactions'] = [
            $this->getTransactionData($orderData),
        ];

        $this->orderRepository->create([$orderData], $this->context);

        $criteria = new Criteria();

        $criteria->addAssociations([
            'lineItems',
            'billingAddress',
            'deliveries.stateMachineState',
            'deliveries.shippingOrderAddress',
            'deliveries.stateMachineState',
            'transactions.stateMachineState',
            'transactions.shippingMethod',
        ]);

        $order = $this->orderRepository->search($criteria, $this->context)->first();

        static::assertInstanceOf(OrderEntity::class, $order);

        return $order;
    }

    /**
     * @return array<string, mixed>
     */
    private function getProductData(string $productId): array
    {
        return [
            'id' => $productId,
            'stock' => 101,
            'productNumber' => 'P101',
            'active' => true,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => 'test product',
                ],
            ],
            'tax' => [
                'name' => '19%',
                'taxRate' => 19.0,
            ],
            'price' => [
                Defaults::CURRENCY => [
                    'gross' => 1.111,
                    'net' => 1.011,
                    'linked' => true,
                    'currencyId' => Defaults::CURRENCY,
                    'listPrice' => [
                        'gross' => 1.111,
                        'net' => 1.011,
                        'linked' => false,
                        'currencyId' => Defaults::CURRENCY,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $orderData
     *
     * @return array<string, mixed>
     */
    private function getTransactionData(array $orderData): array
    {
        $paymentMethod = $this->getContainer()->get('payment_method.repository')->search(new Criteria(), $this->context)->first();
        $paymentMethodId = null;
        if ($paymentMethod instanceof PaymentMethodEntity) {
            $paymentMethodId = $paymentMethod->getId();
        }

        $stateMachineState = $this->getContainer()->get('state_machine_state.repository')->search(new Criteria(), $this->context)->first();
        $stateMachineStateId = null;
        if ($stateMachineState instanceof StateMachineStateEntity) {
            $stateMachineStateId = $stateMachineState->getId();
        }

        return [
            'id' => Uuid::randomHex(),
            'orderId' => $orderData['id'],
            'orderVersionId' => $orderData['versionId'],
            'paymentMethodId' => $paymentMethodId,
            'amount' => [
                'quantity' => 1,
                'taxRules' => [],
                'listPrice' => null,
                'unitPrice' => 20.02,
                'totalPrice' => 20.02,
                'referencePrice' => null,
                'calculatedTaxes' => [],
                'regulationPrice' => null,
            ],
            'stateId' => $stateMachineStateId,
        ];
    }
}
