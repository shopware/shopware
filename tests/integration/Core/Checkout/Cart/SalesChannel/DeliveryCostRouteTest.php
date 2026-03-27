<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('store-api')]
class DeliveryCostRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
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

    public function testDeliveryCostsByProductGetReturnsCurrentShippingMethodOnly(): void
    {
        $this->browser->request('GET', '/store-api/checkout/delivery-cost/' . $this->ids->get('product'));

        $response = $this->decodeResponse();

        static::assertSame([$this->ids->get('shipping-1')], $this->deliveryCostShippingMethodIds($response));
        $deliveryCost = $this->getDeliveryCost($response, $this->ids->get('shipping-1'));

        static::assertNotNull($deliveryCost);
        static::assertSame(5, $deliveryCost['shippingCost']['totalPrice']);
    }

    public function testDeliveryCostsByProductPostWithoutIdsReturnsAllAvailableShippingMethods(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/checkout/delivery-cost/' . $this->ids->get('product'),
        );

        $response = $this->decodeResponse();

        $keys = $this->deliveryCostShippingMethodIds($response);
        sort($keys);

        $expected = [
            $this->ids->get('shipping-1'),
            $this->ids->get('shipping-2'),
            $this->ids->get('shipping-3'),
        ];
        sort($expected);

        static::assertSame($expected, $keys);
    }

    public function testDeliveryCostsCartReturnsCurrentAndAlternativeShippingMethods(): void
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

        $this->browser->request('GET', '/store-api/checkout/delivery-cost/cart');

        $response = $this->decodeResponse();

        $keys = $this->deliveryCostShippingMethodIds($response);
        sort($keys);

        $expected = [$this->ids->get('shipping-1'), $this->ids->get('shipping-2'), $this->ids->get('shipping-3')];
        sort($expected);

        static::assertSame($expected, $keys);
        static::assertNotNull($this->getDeliveryCost($response, $this->ids->get('shipping-1')));
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
    private function deliveryCostShippingMethodIds(array $response): array
    {
        return array_values(array_map(
            static fn (array $deliveryCost): string => $deliveryCost['shippingMethod']['id'],
            $response
        ));
    }

    /**
     * @param list<array<string, mixed>> $response
     *
     * @return array<string, mixed>|null
     */
    private function getDeliveryCost(array $response, string $shippingMethodId): ?array
    {
        foreach ($response as $deliveryCost) {
            if (($deliveryCost['shippingMethod']['id'] ?? null) === $shippingMethodId) {
                return $deliveryCost;
            }
        }

        return null;
    }

    private function createProduct(): void
    {
        $this->productRepository->create([
            [
                'id' => $this->ids->create('product'),
                'productNumber' => $this->ids->create('product-number'),
                'stock' => 100,
                'name' => 'Test product',
                'price' => [[
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 25,
                    'net' => 25,
                    'linked' => false,
                ]],
                'manufacturer' => [
                    'id' => $this->ids->create('manufacturer'),
                    'name' => 'Test manufacturer',
                ],
                'tax' => [
                    'id' => $this->ids->create('tax'),
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
            $this->shippingMethodData('shipping-1', 'shipping_test_1', 1, 5.0),
            $this->shippingMethodData('shipping-2', 'shipping_test_2', 2, 8.0),
            $this->shippingMethodData('shipping-3', 'shipping_test_3', 3, 12.0),
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
