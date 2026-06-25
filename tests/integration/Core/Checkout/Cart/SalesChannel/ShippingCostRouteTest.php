<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('store-api')]
class ShippingCostRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    /**
     * @var EntityRepository<ShippingMethodCollection>
     */
    private EntityRepository $shippingMethodRepository;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->ids->create('sales-channel');
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->shippingMethodRepository = static::getContainer()->get('shipping_method.repository');

        $this->createShippingMethods();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->get('sales-channel'),
            'shippingMethodId' => $this->ids->get('shipping-1'),
            'shippingMethods' => [
                ['id' => $this->ids->get('shipping-1')],
                ['id' => $this->ids->get('shipping-2')],
                ['id' => $this->ids->get('shipping-3')],
            ],
        ]);

        $this->createProduct();
    }

    public function testShippingCostsCartReturnsCurrentAndAlternativeShippingMethods(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/checkout/cart/line-item',
            [
                'items' => [
                    [
                        'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                        'referencedId' => $this->ids->get('product'),
                    ],
                ],
            ]
        );

        $this->browser->request('GET', '/store-api/shipping-cost/cart');

        $response = $this->decodeResponse();

        $keys = $this->shippingMethodIds($response);
        sort($keys);

        $expected = [$this->ids->get('shipping-1'), $this->ids->get('shipping-2'), $this->ids->get('shipping-3')];
        sort($expected);

        static::assertSame($expected, $keys);
        static::assertNotNull($this->getShippingCost($response, $this->ids->get('shipping-1')));
    }

    public function testShippingCostsCartDoesNotChangeSalesChannelContextOrCart(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/checkout/cart/line-item',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode([
                'items' => [
                    [
                        'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                        'referencedId' => $this->ids->get('product'),
                        'quantity' => 2,
                    ],
                ],
            ], \JSON_THROW_ON_ERROR)
        );

        $this->browser->request('GET', '/store-api/context');
        $beforeContext = $this->contextSnapshot($this->decodeResponse());

        $this->browser->request('GET', '/store-api/checkout/cart');
        $beforeCart = $this->cartSnapshot($this->decodeResponse());

        $this->browser->request('GET', '/store-api/shipping-cost/cart');
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser->request('GET', '/store-api/context');
        $afterContext = $this->contextSnapshot($this->decodeResponse());

        $this->browser->request('GET', '/store-api/checkout/cart');
        $afterCart = $this->cartSnapshot($this->decodeResponse());

        static::assertSame($beforeContext, $afterContext);
        static::assertSame($beforeCart, $afterCart);
    }

    public function testShippingCostsCartConsidersDeliveryPromotions(): void
    {
        $code = 'DELIVERY-PROMOTION';
        $context = static::getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), $this->ids->get('sales-channel'));

        $this->createTestFixtureDeliveryPromotion(
            Uuid::randomHex(),
            PromotionDiscountEntity::TYPE_ABSOLUTE,
            2.2,
            static::getContainer(),
            $context,
            $code
        );

        $this->browser->request(
            'POST',
            '/store-api/checkout/cart/line-item',
            [
                'items' => [
                    [
                        'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                        'referencedId' => $this->ids->get('product'),
                    ],
                ],
            ]
        );

        $this->browser->request(
            'POST',
            '/store-api/checkout/cart/line-item',
            [
                'items' => [
                    [
                        'type' => LineItem::PROMOTION_LINE_ITEM_TYPE,
                        'referencedId' => $code,
                    ],
                ],
            ]
        );

        /** @var array{deliveries: list<array{shippingCosts: array{totalPrice: float|int}}>} $cart */
        $cart = $this->decodeResponse();
        static::assertCount(2, $cart['deliveries']);
        static::assertEqualsWithDelta(3.0, $cart['deliveries'][0]['shippingCosts']['totalPrice'] + $cart['deliveries'][1]['shippingCosts']['totalPrice'], 0.001);

        $this->browser->request('GET', '/store-api/shipping-cost/cart');

        $response = $this->decodeResponse();
        $currentShippingMethod = $this->getShippingCost($response, $this->ids->get('shipping-1'));
        $alternativeShippingMethod = $this->getShippingCost($response, $this->ids->get('shipping-2'));

        static::assertNotNull($currentShippingMethod);
        static::assertNotNull($alternativeShippingMethod);
        static::assertEqualsWithDelta(3.0, $currentShippingMethod['shippingCost']['totalPrice'], 0.001);
        static::assertEqualsWithDelta(6.3, $alternativeShippingMethod['shippingCost']['totalPrice'], 0.001);
    }

    public function testShippingCostsCartReturnsNoShippingCostsForDigitalOnlyCart(): void
    {
        $this->createProduct(
            'digital-product',
            'digital-product-number',
            'digital-manufacturer',
            'digital-tax',
            'Digital product',
            ProductDefinition::TYPE_DIGITAL
        );

        $this->browser->request(
            'POST',
            '/store-api/checkout/cart/line-item',
            [
                'items' => [
                    [
                        'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                        'referencedId' => $this->ids->get('digital-product'),
                    ],
                ],
            ]
        );

        /** @var array{deliveries?: list<array<string, mixed>>} $cart */
        $cart = $this->decodeResponse();
        static::assertSame([], $cart['deliveries'] ?? []);

        $this->browser->request('GET', '/store-api/shipping-cost/cart');

        $response = $this->decodeResponse();
        static::assertSame([], $response);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeResponse(): array
    {
        return json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @param list<array<string, mixed>> $response
     *
     * @return list<string>
     */
    private function shippingMethodIds(array $response): array
    {
        return array_values(array_map(
            static fn (array $shippingCost): string => $shippingCost['shippingMethod']['id'],
            $response
        ));
    }

    /**
     * @param list<array<string, mixed>> $response
     *
     * @return array<string, mixed>|null
     */
    private function getShippingCost(array $response, string $shippingMethodId): ?array
    {
        foreach ($response as $shippingCost) {
            if (($shippingCost['shippingMethod']['id'] ?? null) === $shippingMethodId) {
                return $shippingCost;
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $response
     *
     * @return array<string, mixed>
     */
    private function contextSnapshot(array $response): array
    {
        static::assertArrayHasKey('token', $response);
        static::assertArrayHasKey('shippingMethod', $response);
        static::assertArrayHasKey('paymentMethod', $response);

        return [
            'token' => $response['token'],
            'shippingMethodId' => $response['shippingMethod']['id'],
            'paymentMethodId' => $response['paymentMethod']['id'],
            'ruleIds' => $response['ruleIds'] ?? null,
        ];
    }

    /**
     * @param list<array<string, mixed>> $response
     *
     * @return array<string, mixed>
     */
    private function cartSnapshot(array $response): array
    {
        static::assertArrayHasKey('token', $response);
        static::assertArrayHasKey('price', $response);

        $lineItems = array_map(
            static fn (array $lineItem): array => [
                'id' => $lineItem['id'],
                'referencedId' => $lineItem['referencedId'],
                'quantity' => $lineItem['quantity'],
            ],
            $response['lineItems'] ?? []
        );

        $deliveries = array_map(
            static fn (array $delivery): array => [
                'shippingMethodId' => $delivery['shippingMethod']['id'],
                'shippingTotal' => $delivery['shippingCosts']['totalPrice'],
            ],
            $response['deliveries'] ?? []
        );

        return [
            'token' => $response['token'],
            'lineItems' => array_values($lineItems),
            'deliveries' => array_values($deliveries),
            'priceTotal' => $response['price']['totalPrice'],
        ];
    }

    private function createProduct(
        string $idKey = 'product',
        string $productNumberKey = 'product-number',
        string $manufacturerKey = 'manufacturer',
        string $taxKey = 'tax',
        string $name = 'Test product',
        string $type = ProductDefinition::TYPE_PHYSICAL
    ): void {
        $this->productRepository->create([
            [
                'id' => $this->ids->create($idKey),
                'productNumber' => $this->ids->create($productNumberKey),
                'stock' => 100,
                'name' => $name,
                'type' => $type,
                'price' => [[
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 25,
                    'net' => 25,
                    'linked' => false,
                ]],
                'manufacturer' => [
                    'id' => $this->ids->create($manufacturerKey),
                    'name' => 'Test manufacturer',
                ],
                'tax' => [
                    'id' => $this->ids->create($taxKey),
                    'taxRate' => 0,
                    'name' => 'Zero tax',
                ],
                'active' => true,
                'visibilities' => [
                    [
                        'salesChannelId' => $this->ids->get('sales-channel'),
                        'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ],
        ], Context::createDefaultContext());
    }

    private function createShippingMethods(): void
    {
        $this->shippingMethodRepository->create([
            $this->shippingMethodData('shipping-1', 'shipping_test_1', 1, 5.2),
            $this->shippingMethodData('shipping-2', 'shipping_test_2', 2, 8.5),
            $this->shippingMethodData('shipping-3', 'shipping_test_3', 3, 12.7),
        ], Context::createDefaultContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function shippingMethodData(string $idKey, string $technicalName, int $position, float $price): array
    {
        return [
            'id' => $this->ids->create($idKey),
            'name' => 'Shipping ' . $position,
            'technicalName' => $technicalName,
            'active' => true,
            'position' => $position,
            'bindShippingfree' => false,
            'deliveryTime' => [
                'id' => Uuid::randomHex(),
                'name' => 'testDeliveryTime-' . $position,
                'min' => 1,
                'max' => 3,
                'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
            ],
            'prices' => [
                [
                    'id' => Uuid::randomHex(),
                    'calculation' => 1,
                    'quantityStart' => 1,
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => $price,
                            'gross' => $price,
                            'linked' => false,
                        ],
                    ],
                ],
            ],
        ];
    }
}
