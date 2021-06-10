<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\CartAmountRule;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;

/**
 * @group store-api
 * @group cart
 */
class CartItemUpdateRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

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

    public function testChangeQuantity(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'type' => 'product',
                            'referencedId' => $this->ids->get('p1'),
                        ],
                    ],
                ])
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(10, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);
        static::assertSame(1, $response['lineItems'][0]['quantity']);

        $this->browser
            ->request(
                'PATCH',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'quantity' => 2,
                        ],
                    ],
                ])
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(2, $response['lineItems'][0]['quantity']);
        static::assertSame(20, $response['price']['totalPrice']);
    }

    public function testChangePayload(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'type' => 'product',
                            'referencedId' => $this->ids->get('p1'),
                        ],
                    ],
                ])
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(10, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);
        static::assertSame(1, $response['lineItems'][0]['quantity']);

        $this->browser
            ->request(
                'PATCH',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'payload' => [
                                'test' => 'test',
                            ],
                        ],
                    ],
                ])
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('test', $response['lineItems'][0]['payload']);

        static::assertSame('test', $response['lineItems'][0]['payload']['test']);
        static::assertSame(10, $response['price']['totalPrice']);
    }

    public function testChangeQuantityWhenNotStackable(): void
    {
        $this->enableAdminAccess();

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'type' => 'custom',
                            'label' => 'Test',
                            'referencedId' => $this->ids->get('p1'),
                            'stackable' => false,
                            'priceDefinition' => [
                                'price' => 100.0,
                                'type' => 'quantity',
                                'precision' => 1,
                                'taxRules' => [
                                    [
                                        'taxRate' => 5,
                                        'percentage' => 100,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ])
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), json_encode($response));

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(105, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);
        static::assertSame(1, $response['lineItems'][0]['quantity']);

        $this->browser
            ->request(
                'PATCH',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'quantity' => 2,
                        ],
                    ],
                ])
            );

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('CHECKOUT__CART_LINEITEM_NOT_STACKABLE', $response['errors'][0]['code']);
    }

    public function testUpdateByTypeCredit(): void
    {
        $this->enableAdminAccess();

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'referencedId' => $this->ids->get('p1'),
                            'label' => 'item',
                            'type' => 'credit',
                            'priceDefinition' => [
                                'price' => 100.0,
                                'type' => 'absolute',
                                'absolute' => 1.0,
                            ],
                            'stackable' => true,
                        ],
                    ],
                ])
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser
            ->request(
                'PATCH',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'label' => 'item update',
                        ],
                    ],
                ])
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame('item update', $response['lineItems'][0]['label']);
    }

    public function testUpdateCartShouldChangePriceWithPriceDefinition(): void
    {
        $this->enableAdminAccess();

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'referencedId' => $this->ids->get('p1'),
                            'label' => 'Test',
                            'type' => 'product',
                            'priceDefinition' => null,
                            'stackable' => true,
                            'quantity' => 1,
                            'removable' => true,
                            'salesChannelId' => $this->ids->get('sales-channel'),
                        ],
                    ],
                ])
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser
            ->request(
                'PATCH',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'referencedId' => $this->ids->get('p1'),
                            'label' => 'Test',
                            'type' => 'product',
                            'priceDefinition' => [
                                'apiAlias' => 'cart_price_quantity',
                                'isCalculated' => true,
                                'listPrice' => null,
                                'precision' => 2,
                                'price' => 750,
                                'quantity' => 1,
                                'referencePriceDefinition' => null,
                                'taxRules' => [
                                    [
                                        'taxRate' => 5,
                                        'percentage' => 100,
                                    ],
                                ],
                                'type' => 'quantity',
                            ],
                            'stackable' => true,
                            'quantity' => 1,
                            'removable' => true,
                            'salesChannelId' => $this->ids->get('sales-channel'),
                        ],
                    ],
                ])
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame(750, $response['lineItems'][0]['price']['unitPrice']);
    }

    public function testUpdateCartShouldShowAdvancedPriceWithoutPriceDefinition(): void
    {
        $this->enableAdminAccess();

        $this->ids->context->setRuleIds([
            $this->ids->create('rule-a'),
            $this->ids->create('rule-b'),
        ]);

        $data = [
            'id' => $this->ids->create('product'),
            'productNumber' => $this->ids->get('product'),
            'stock' => 10,
            'name' => 'Test',
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
            ],
            'manufacturer' => ['id' => $this->ids->create('manufacturerId'), 'name' => 'test'],
            'tax' => ['id' => $this->ids->create('tax'), 'taxRate' => 17, 'name' => 'with id'],
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
            'prices' => [
                [
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'ruleId' => $this->ids->get('rule-a'),
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 1111, 'net' => 1111, 'linked' => false],
                    ],
                ],
                [
                    'quantityStart' => 1,
                    'ruleId' => $this->ids->get('rule-b'),
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 2222, 'net' => 2222, 'linked' => false],
                    ],
                ],
            ],
        ];

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $this->ids->get('rule-a'), 'name' => 'testA', 'priority' => 1, 'payload' => serialize(new AndRule([new CartAmountRule(Rule::OPERATOR_GTE, 0)]))],
            ['id' => $this->ids->get('rule-b'), 'name' => 'testB', 'priority' => 2, 'payload' => serialize(new AndRule([new CartAmountRule(Rule::OPERATOR_NEQ, 0)]))],
        ], $this->ids->context);

        $this->getContainer()->get('product.repository')->create(
            [$data],
            $this->ids->context
        );

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('product'),
                            'referencedId' => $this->ids->get('product'),
                            'label' => 'Test',
                            'type' => 'product',
                            'priceDefinition' => null,
                            'stackable' => true,
                            'quantity' => 1,
                            'removable' => true,
                            'salesChannelId' => $this->ids->get('sales-channel'),
                        ],
                    ],
                ])
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent());

        $this->browser
            ->request(
                'PATCH',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('product'),
                            'referencedId' => $this->ids->get('product'),
                            'label' => 'Test',
                            'type' => 'product',
                            'priceDefinition' => null,
                            'stackable' => true,
                            'quantity' => 1,
                            'removable' => true,
                            'salesChannelId' => $this->ids->get('sales-channel'),
                        ],
                    ],
                ])
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame(2222, $response['lineItems'][0]['price']['unitPrice']);
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
                'name' => 'Test2',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->ids->create('manufacturerId'), 'name' => 'test'],
                'tax' => ['id' => $this->ids->create('tax'), 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
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
