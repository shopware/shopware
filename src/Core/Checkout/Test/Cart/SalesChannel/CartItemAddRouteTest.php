<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionTestFixtureBehaviour;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;

/**
 * @group store-api
 * @group cart
 */
class CartItemAddRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use PromotionTestFixtureBehaviour;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var \Shopware\Core\Framework\DataAbstractionLayer\EntityRepository
     */
    private $productRepository;

    public function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));
        $this->productRepository = $this->getContainer()->get('product.repository');

        $this->createTestData();
    }

    public function testFillCartProducts(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'label' => 'foo',
                            'type' => 'product',
                            'referencedId' => $this->ids->get('p1'),
                        ],
                    ],
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(10, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);
        static::assertSame('Test', $response['lineItems'][0]['label']);
    }

    public function testFillCartOutOfStock(): void
    {
        $this->browser->setServerParameter('HTTP_sw-include-seo-urls', '1');
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $this->ids->get('p3'),
                            'label' => 'foo',
                            'type' => 'product',
                            'referencedId' => $this->ids->get('p3'),
                        ],
                    ],
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(0, $response['price']['totalPrice']);
        static::assertCount(0, $response['lineItems']);
        static::assertCount(1, $response['errors']);
        static::assertSame('The product Test is no longer available', array_column($response['errors'], 'message')[0]);
    }

    public function testFillCartMultipleProducts(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'type' => 'product',
                            'referencedId' => $this->ids->get('p1'),
                        ],
                        [
                            'id' => $this->ids->get('p2'),
                            'type' => 'product',
                            'referencedId' => $this->ids->get('p2'),
                        ],
                    ],
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(20, $response['price']['totalPrice']);
        static::assertCount(2, $response['lineItems']);
        static::assertSame('Test', $response['lineItems'][0]['label']);
    }

    public function testAddCustomWithoutPermission(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'label' => 'Test',
                            'type' => 'credit',
                            'priceDefinition' => [
                                'price' => 100.0,
                                'type' => 'absolute',
                                'absolute' => 1.0,
                            ],
                        ],
                    ],
                ]
            );

        static::assertSame(403, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('INSUFFICIENT_PERMISSION', $response['errors'][0]['code']);
    }

    public function testAddCustomWithPermission(): void
    {
        $this->enableAdminAccess();
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'label' => 'Test',
                            'type' => 'credit',
                            'priceDefinition' => [
                                'price' => 100.0,
                                'type' => 'absolute',
                                'absolute' => 1.0,
                            ],
                        ],
                    ],
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(100, $response['price']['totalPrice']);
    }

    public function testCustomTaxIncludedInShippingCostTaxes(): void
    {
        $this->enableAdminAccess();

        $taxForProductItem = 10;
        $taxForCustomItem = 15;

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'label' => 'product item',
                            'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                            'priceDefinition' => [
                                'price' => 100,
                                'taxRules' => [[
                                    'taxRate' => $taxForProductItem,
                                    'percentage' => 100,
                                ]],
                                'type' => 'quantity',
                            ],
                            'referencedId' => $this->ids->get('p1'),
                        ],
                        [
                            'label' => 'custom item',
                            'type' => LineItem::CUSTOM_LINE_ITEM_TYPE,
                            'tax' => [
                                'id' => Uuid::randomHex(),
                                'taxRate' => $taxForCustomItem,
                                'name' => 'taxCustomItem',
                            ],
                            'priceDefinition' => [
                                'price' => 150,
                                'taxRules' => [[
                                    'taxRate' => $taxForCustomItem,
                                    'percentage' => 100,
                                ]],
                                'type' => 'quantity',
                            ],
                        ],
                    ],
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        $cart = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('deliveries', $cart);
        static::assertCount(1, $deliveries = $cart['deliveries']);
        static::assertNotEmpty($shippingCost = $deliveries[0]['shippingCosts']);
        static::assertCount(2, $shippingCostCalculatedTaxes = $shippingCost['calculatedTaxes']);

        //assert there is shipping cost calculated taxes for product and custom items in cart
        $calculatedTaxForCustomItem = array_filter($shippingCostCalculatedTaxes, function ($tax) use ($taxForCustomItem) {
            return $tax['taxRate'] === $taxForCustomItem;
        });

        static::assertNotEmpty($calculatedTaxForCustomItem);
        static::assertCount(1, $calculatedTaxForCustomItem);

        $calculatedTaxForProductItem = array_filter($shippingCostCalculatedTaxes, function ($tax) use ($taxForProductItem) {
            return $tax['taxRate'] === $taxForProductItem;
        });

        static::assertNotEmpty($calculatedTaxForProductItem);
        static::assertCount(1, $calculatedTaxForProductItem);
    }

    public function testAddPromotion(): void
    {
        $promotionId = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), $this->ids->get('sales-channel'));

        $this->createTestFixtureProduct($productId, 800, 19, $this->getContainer(), $context);

        $this->createPromotion(
            $promotionId,
            $code,
            $this->getContainer()->get('promotion.repository'),
            $context
        );

        $this->createTestFixtureDiscount($promotionId, PromotionDiscountEntity::TYPE_ABSOLUTE, PromotionDiscountEntity::SCOPE_CART, 10, null, $this->getContainer(), $context);

        // Add product
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $productId,
                            'type' => 'product',
                            'referencedId' => $productId,
                        ],
                    ],
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        // Add code
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'type' => 'promotion',
                            'referencedId' => $code,
                        ],
                    ],
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(790, $response['price']['totalPrice']);
        static::assertCount(2, $response['lineItems']);
        static::assertSame('Test', $response['lineItems'][0]['label']);
    }

    private function createTestData(): void
    {
        $this->productRepository->create([
            [
                'id' => $this->ids->create('p1'),
                'productNumber' => $this->ids->get('p1'),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->ids->create('manufacturerId'), 'name' => 'test'],
                'tax' => ['id' => $this->ids->create('tax'), 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $this->ids->context);

        $this->productRepository->create([
            [
                'id' => $this->ids->create('p2'),
                'productNumber' => $this->ids->get('p2'),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->ids->get('manufacturerId'), 'name' => 'test'],
                'tax' => ['id' => $this->ids->get('tax'), 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $this->ids->context);

        $this->productRepository->create([
            [
                'id' => $this->ids->create('p3'),
                'productNumber' => $this->ids->get('p3'),
                'stock' => 0,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->ids->get('manufacturerId'), 'name' => 'test'],
                'tax' => ['id' => $this->ids->get('tax'), 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'isCloseout' => true,
                'visibilities' => [
                    ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $this->ids->context);
    }

    private function enableAdminAccess(): void
    {
        $token = $this->browser->getServerParameter('HTTP_SW_CONTEXT_TOKEN');
        $payload = $this->getContainer()->get(SalesChannelContextPersister::class)->load($token, $this->ids->get('sales-channel'));

        $payload[SalesChannelContextService::PERMISSIONS] = [ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES => true];

        $this->getContainer()->get(SalesChannelContextPersister::class)->save($token, $payload, $this->ids->get('sales-channel'));
    }
}
