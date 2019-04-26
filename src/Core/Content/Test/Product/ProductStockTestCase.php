<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;

class ProductStockTestCase extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var EntityRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    protected $orderDeliveryRepository;

    /**
     * @var StateMachineRegistry
     */
    protected $stateMachineRegistry;

    /**
     * @var TagAwareAdapterInterface
     */
    protected $cache;

    /**
     * @var EntityCacheKeyGenerator
     */
    protected $entityCacheKeyGenerator;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->orderDeliveryRepository = $this->getContainer()->get('order_delivery.repository');
        $this->stateMachineRegistry = $this->getContainer()->get(StateMachineRegistry::class);
        $this->cache = $this->getContainer()->get('shopware.cache');
        $this->entityCacheKeyGenerator = $this->getContainer()->get(EntityCacheKeyGenerator::class);
    }

    protected function createTestProduct(string $productId, int $stock): ProductEntity
    {
        $this->productRepository->create(
            [
                [
                    'id' => $productId,
                    'productNumber' => Uuid::randomHex(),
                    'stock' => $stock,
                    'name' => 'Test',
                    'price' => [
                        'gross' => 15,
                        'net' => 10,
                        'linked' => false,
                    ],
                    'manufacturer' => [
                        'id' => Uuid::randomHex(),
                        'name' => 'Dummy manufacturer',
                    ],
                    'tax' => [
                        'id' => Uuid::randomHex(),
                        'name' => '15 %',
                        'taxRate' => 15,
                    ],
                    'categories' => [
                        [
                            'id' => Uuid::randomHex(),
                            'name' => 'Dummy category',
                        ],
                    ],
                ],
            ],
            $this->context
        );

        return $this->readProductFromDatabase($productId);
    }

    protected function createTestOrder(array $orderedProductDescriptors): OrderEntity
    {
        $salesChannelId = Defaults::SALES_CHANNEL;
        $currenyId = Defaults::CURRENCY;
        $orderId = Uuid::randomHex();
        $paymentMethodId = $this->getValidPaymentMethodId();
        $shippingMethodId = $this->getValidShippingMethodId();
        $salutationId = $this->getValidSalutationId();
        $countryId = $this->getValidCountryId();
        $countryStateId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $orderStateOpen = $this->stateMachineRegistry->getStateByTechnicalName(OrderStates::STATE_MACHINE, OrderStates::STATE_OPEN, $this->context);
        $orderDeliveryStateOpen = $this->stateMachineRegistry->getStateByTechnicalName(OrderDeliveryStates::STATE_MACHINE, OrderDeliveryStates::STATE_OPEN, $this->context);

        $orderLineItems = [];
        $orderDeliveryPositions = [];
        foreach ($orderedProductDescriptors as $orderedProductDescriptor) {
            $orderLineItemId = Uuid::randomHex();
            $product = $orderedProductDescriptor['product'];
            $orderedQuantity = $orderedProductDescriptor['orderedQuantity'];

            $orderLineItems[] = [
                'id' => $orderLineItemId,
                'identifier' => 'test',
                'quantity' => $orderedQuantity,
                'type' => 'product',
                'label' => $product->getName(),
                'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'priceDefinition' => new QuantityPriceDefinition(10, new TaxRuleCollection(), 2),
                'priority' => 100,
                'good' => true,
                'payload' => [
                    'id' => $product->getId(),
                    'productNumber' => $product->getProductNumber(),
                ],
            ];

            $orderDeliveryPositions[] = [
                'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), $orderedQuantity),
                'orderLineItemId' => $orderLineItemId,
            ];
        }

        $this->orderRepository->create(
            [
                [
                    'id' => $orderId,
                    'orderDate' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_FORMAT),
                    'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
                    'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'stateId' => $orderStateOpen->getId(),
                    'paymentMethodId' => $paymentMethodId,
                    'currencyId' => $currenyId,
                    'currencyFactor' => 1,
                    'salesChannelId' => $salesChannelId,
                    'lineItems' => $orderLineItems,
                    'deliveries' => [
                        [
                            'stateId' => $orderDeliveryStateOpen->getId(),
                            'shippingMethodId' => $shippingMethodId,
                            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                            'shippingDateEarliest' => date(DATE_ISO8601),
                            'shippingDateLatest' => date(DATE_ISO8601),
                            'shippingOrderAddress' => [
                                'salutationId' => $salutationId,
                                'firstName' => 'Floy',
                                'lastName' => 'Glover',
                                'zipcode' => '59438-0403',
                                'city' => 'Stellaberg',
                                'street' => 'street',
                                'country' => [
                                    'name' => 'kasachstan',
                                    'id' => $countryId,
                                ],
                            ],
                            'positions' => $orderDeliveryPositions,
                        ],
                    ],
                    'orderCustomer' => [
                        'email' => 'test@example.com',
                        'firstName' => 'Noe',
                        'lastName' => 'Hill',
                        'salutationId' => $salutationId,
                        'title' => 'Doc',
                        'customerNumber' => 'Test',
                        'customer' => [
                            'email' => 'test@example.com',
                            'firstName' => 'Noe',
                            'lastName' => 'Hill',
                            'salutationId' => $salutationId,
                            'title' => 'Doc',
                            'customerNumber' => 'Test',
                            'guest' => true,
                            'group' => ['name' => 'testse2323'],
                            'defaultPaymentMethodId' => $paymentMethodId,
                            'salesChannelId' => $salesChannelId,
                            'defaultBillingAddressId' => $addressId,
                            'defaultShippingAddressId' => $addressId,
                            'addresses' => [
                                [
                                    'id' => $addressId,
                                    'salutationId' => $salutationId,
                                    'firstName' => 'Floy',
                                    'lastName' => 'Glover',
                                    'zipcode' => '59438-0403',
                                    'city' => 'Stellaberg',
                                    'street' => 'street',
                                    'countryStateId' => $countryStateId,
                                    'country' => [
                                        'name' => 'kasachstan',
                                        'id' => $countryId,
                                        'states' => [
                                            [
                                                'id' => $countryStateId,
                                                'name' => 'oklahoma',
                                                'shortCode' => 'OH',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'addresses' => [
                        [
                            'salutationId' => $salutationId,
                            'firstName' => 'Floy',
                            'lastName' => 'Glover',
                            'zipcode' => '59438-0403',
                            'city' => 'Stellaberg',
                            'street' => 'street',
                            'countryId' => $countryId,
                            'id' => $addressId,
                        ],
                    ],
                    'billingAddressId' => $addressId,
                ],
            ],
            $this->context
        );

        return $this->orderRepository->search((new Criteria([$orderId]))->addAssociation('deliveries'), $this->context)->getEntities()->first();
    }

    protected function readProductFromDatabase(string $productId): ProductEntity
    {
        // Ensure the product is read from the database by invalidating its entity cache entry
        $criteria = new Criteria([$productId]);
        $cacheKey = $this->entityCacheKeyGenerator->getEntityContextCacheKey($productId, ProductDefinition::class, $this->context, $criteria);
        $this->cache->invalidateTags([$cacheKey]);

        return $this->productRepository->search($criteria, $this->context)->getEntities()->first();
    }
}
