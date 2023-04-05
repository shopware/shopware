<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 *
 * @group store-api
 * @group cart
 */
#[Package('checkout')]
class CartDeleteRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    private EntityRepository $productRepository;

    private AbstractSalesChannelContextFactory $salesChannelFactory;

    private AbstractCartPersister $cartPersister;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));

        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->cartPersister = $this->getContainer()->get(CartPersister::class);
        $this->salesChannelFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
    }

    public function testEmptyCart(): void
    {
        $this->browser
            ->request(
                'DELETE',
                '/store-api/checkout/cart',
                [
                ]
            );

        static::assertSame(204, $this->browser->getResponse()->getStatusCode());

        $this->browser
            ->request(
                'GET',
                '/store-api/checkout/cart',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(0, $response['price']['totalPrice']);
    }

    public function testFilledCart(): void
    {
        // Fill
        $this->productRepository->create([
            [
                'id' => $this->ids->create('productId'),
                'productNumber' => $this->ids->create('productNumber'),
                'stock' => 1,
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

        $cart = new Cart($this->ids->get('token'));
        $cart->add(new LineItem($this->ids->create('productId'), LineItem::PRODUCT_LINE_ITEM_TYPE, $this->ids->get('productId')));

        $this->cartPersister->save($cart, $this->salesChannelFactory->create($this->ids->get('token'), $this->ids->get('sales-channel')));

        // Check
        $this->browser
            ->request(
                'GET',
                '/store-api/checkout/cart',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(10, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);
        static::assertSame('Test', $response['lineItems'][0]['label']);

        // Delete cart
        $this->browser
            ->request(
                'DELETE',
                '/store-api/checkout/cart',
                [
                ]
            );

        static::assertSame(204, $this->browser->getResponse()->getStatusCode());

        // Check
        $this->browser
            ->request(
                'GET',
                '/store-api/checkout/cart',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(0, $response['price']['totalPrice']);
    }
}
