<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Test\Customer\Rule\OrderFixture;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\OrderSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class OrderSerializerTest extends TestCase
{
    use IntegrationTestBehaviour;

    use OrderFixture;

    private OrderSerializer $serializer;

    private EntityRepositoryInterface $orderRepository;

    protected function setUp(): void
    {
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $serializerRegistry = $this->getContainer()->get(SerializerRegistry::class);

        $this->serializer = new OrderSerializer();

        $this->serializer->setRegistry($serializerRegistry);
    }

    public function testSerializeOrder(): void
    {
        $order = $this->createOrder();
        $orderDefinition = $this->getContainer()->get('order.repository')->getDefinition();
        $config = new Config([], [], []);

        $serialized = iterator_to_array($this->serializer->serialize($config, $orderDefinition, $order));

        static::assertNotEmpty($serialized);

        // assert values
        static::assertSame($serialized['id'], $order->getId());
        static::assertSame($serialized['orderNumber'], $order->getOrderNumber());
        static::assertSame($serialized['salesChannelId'], $order->getSalesChannelId());

        static::assertSame($serialized['orderCustomer']->getFirstName(), $order->getOrderCustomer()->getFirstName());
        static::assertSame($serialized['orderCustomer']->getLastName(), $order->getOrderCustomer()->getLastName());
        static::assertSame($serialized['orderCustomer']->getEmail(), $order->getOrderCustomer()->getEmail());

        static::assertSame($serialized['billingAddress']->getStreet(), $order->getBillingAddress()->getStreet());
        static::assertSame($serialized['billingAddress']->getZipcode(), $order->getBillingAddress()->getZipcode());
        static::assertSame($serialized['billingAddress']->getCity(), $order->getBillingAddress()->getCity());
        static::assertSame($serialized['billingAddress']->getCompany(), $order->getBillingAddress()->getCompany());
        static::assertSame($serialized['billingAddress']->getDepartment(), $order->getBillingAddress()->getDepartment());
        static::assertSame($serialized['billingAddress']->getCountryId(), $order->getBillingAddress()->getCountryId());
        static::assertSame($serialized['billingAddress']->getCountryStateId(), $order->getBillingAddress()->getCountryStateId());

        /** @var OrderAddressEntity $shippingAddress */
        $shippingAddress = $order->getDeliveries()->first()->getShippingOrderAddress();

        static::assertSame($serialized['deliveries']['shippingOrderAddress']['street'], $shippingAddress->getStreet());
        static::assertSame($serialized['deliveries']['shippingOrderAddress']['zipcode'], $shippingAddress->getZipcode());
        static::assertSame($serialized['deliveries']['shippingOrderAddress']['city'], $shippingAddress->getCity());
        static::assertSame($serialized['deliveries']['shippingOrderAddress']['company'], $shippingAddress->getCompany());
        static::assertSame($serialized['deliveries']['shippingOrderAddress']['department'], $shippingAddress->getDepartment());
        static::assertSame($serialized['deliveries']['shippingOrderAddress']['countryId'], $shippingAddress->getCountryId());
        static::assertSame($serialized['deliveries']['shippingOrderAddress']['countryStateId'], $shippingAddress->getCountryStateId());

        static::assertSame($serialized['lineItems'], '1x ' . $order->getLineItems()->first()->getProductId());

        static::assertSame($serialized['amountTotal'], $order->getAmountTotal());
        static::assertSame($serialized['stateId'], $order->getStateId());
        static::assertSame($serialized['orderDateTime'], $order->getOrderDateTime()->format('Y-m-d\Th:i:s.vP'));
    }

    private function createOrder(): OrderEntity
    {
        // create product
        $productId = Uuid::randomHex();
        $product = $this->getProductData($productId);

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $productRepository->create([$product], Context::createDefaultContext());

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, Context::createDefaultContext())[0];

        $orderData['lineItems'][0]['productId'] = $productId;

        $this->orderRepository->create([$orderData], Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addAssociation('lineItems')
            ->addAssociation('billingAddress')
            ->addAssociation('deliveries');

        return $this->orderRepository->search($criteria, Context::createDefaultContext())->first();
    }

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
}
