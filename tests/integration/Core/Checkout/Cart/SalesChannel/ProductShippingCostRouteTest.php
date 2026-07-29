<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
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
class ProductShippingCostRouteTest extends TestCase
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

    public function testShippingCostsByProductGetReturnsCurrentShippingMethodOnly(): void
    {
        $this->browser->request(
            'GET',
            '/store-api/shipping-cost/product/' . $this->ids->get('product') . '?ids[]=' . $this->ids->get('shipping-1'),
        );

        $response = $this->decodeResponse();

        static::assertSame([$this->ids->get('shipping-1')], $this->shippingMethodIds($response));
        $shippingCost = $this->getShippingCost($response, $this->ids->get('shipping-1'));

        static::assertNotNull($shippingCost);
        static::assertSame(5.2, $shippingCost['shippingCost']['totalPrice']);
    }

    public function testShippingCostsByProductSkipsAutomaticDeliveryPromotions(): void
    {
        $context = static::getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), $this->ids->get('sales-channel'));

        $this->createTestFixtureDeliveryPromotion(
            Uuid::randomHex(),
            PromotionDiscountEntity::TYPE_ABSOLUTE,
            2.2,
            static::getContainer(),
            $context,
            null
        );

        $this->browser->request(
            'GET',
            '/store-api/shipping-cost/product/' . $this->ids->get('product') . '?ids[]=' . $this->ids->get('shipping-1'),
        );

        $response = $this->decodeResponse();
        $shippingCost = $this->getShippingCost($response, $this->ids->get('shipping-1'));

        static::assertNotNull($shippingCost);
        static::assertSame(5.2, $shippingCost['shippingCost']['totalPrice']);
    }

    public function testShippingCostsByProductGetWithoutIdsReturnsAllAvailableShippingMethods(): void
    {
        $this->browser->request(
            'GET',
            '/store-api/shipping-cost/product/' . $this->ids->get('product'),
        );

        $response = $this->decodeResponse();

        $keys = $this->shippingMethodIds($response);
        sort($keys);

        $expected = [
            $this->ids->get('shipping-1'),
            $this->ids->get('shipping-2'),
            $this->ids->get('shipping-3'),
        ];
        sort($expected);

        static::assertSame($expected, $keys);
        static::assertSame(5.2, $response[0]['shippingCost']['unitPrice']);
        static::assertSame(8.5, $response[1]['shippingCost']['unitPrice']);
        static::assertSame(12.7, $response[2]['shippingCost']['unitPrice']);
    }

    public function testShippingCostsByProductReturnsNoShippingCostsForDigitalProduct(): void
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
            'GET',
            '/store-api/shipping-cost/product/' . $this->ids->get('digital-product'),
        );

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
