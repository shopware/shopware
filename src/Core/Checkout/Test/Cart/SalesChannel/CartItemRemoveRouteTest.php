<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 *
 * @group store-api
 * @group cart
 */
#[Package('checkout')]
class CartItemRemoveRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    private EntityRepository $productRepository;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));
        $this->productRepository = $this->getContainer()->get('product.repository');

        $this->createTestData();
    }

    public function testRemoveWithoutRemoveable(): void
    {
        // Removeable is only allowed to set when permission is given
        $this->enableAdminAccess();
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                (string) json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'type' => 'custom',
                            'label' => 'Test',
                            'referencedId' => $this->ids->get('p1'),
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
                            'removable' => false,
                        ],
                    ],
                ])
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(105, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);

        // Remove
        $this->browser
            ->request(
                'DELETE',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                (string) json_encode([
                    'ids' => [
                        $this->ids->get('p1'),
                    ],
                ])
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('CHECKOUT__CART_LINE_ITEM_NOT_REMOVABLE', $response['errors'][0]['code']);
    }

    public function testRemove(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                (string) json_encode([
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

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(10, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);

        // Remove
        $this->browser
            ->request(
                'DELETE',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                (string) json_encode([
                    'ids' => [
                        $this->ids->get('p1'),
                    ],
                ])
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(0, $response['lineItems']);
        static::assertSame(0, $response['price']['totalPrice']);
    }

    public function testRemoveByTypeCredit(): void
    {
        $this->enableAdminAccess();
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                (string) json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'referencedId' => $this->ids->get('p1'),
                            'label' => 'Test',
                            'type' => 'credit',
                            'priceDefinition' => [
                                'price' => 100.0,
                                'type' => 'absolute',
                                'absolute' => 1.0,
                            ],
                            'removable' => true,
                        ],
                    ],
                ])
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        // Remove
        $this->browser
            ->request(
                'DELETE',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                (string) json_encode([
                    'ids' => [
                        $this->ids->get('p1'),
                    ],
                ])
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(0, $response['lineItems']);
        static::assertSame(0, $response['price']['totalPrice']);
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
        ], Context::createDefaultContext());
    }

    private function enableAdminAccess(): void
    {
        $token = $this->browser->getServerParameter('HTTP_SW_CONTEXT_TOKEN');
        $payload = $this->getContainer()->get(SalesChannelContextPersister::class)->load($token, $this->ids->get('sales-channel'));

        $payload[SalesChannelContextService::PERMISSIONS] = [ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES => true];

        $this->getContainer()->get(SalesChannelContextPersister::class)->save($token, $payload, $this->ids->get('sales-channel'));
    }
}
