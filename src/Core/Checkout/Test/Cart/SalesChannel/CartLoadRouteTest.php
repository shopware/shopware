<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;

/**
 * @group store-api
 */
class CartLoadRouteTest extends TestCase
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

    /**
     * @var AbstractSalesChannelContextFactory
     */
    private $salesChannelFactory;

    /**
     * @var CartPersisterInterface
     */
    private $cartPersister;

    public function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->cartPersister = $this->getContainer()->get(CartPersister::class);
        $this->salesChannelFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
    }

    public function testEmptyCart(): void
    {
        $this->browser
            ->request(
                'GET',
                '/store-api/checkout/cart',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(0, $response['price']['totalPrice']);
    }

    public function testFilledCart(): void
    {
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
        ], $this->ids->context);

        $cart = new Cart('sales-channel', $this->ids->create('token'));
        $cart->add(new LineItem($this->ids->create('productId'), LineItem::PRODUCT_LINE_ITEM_TYPE, $this->ids->get('productId')));

        $this->cartPersister->save($cart, $this->salesChannelFactory->create($this->ids->get('token'), $this->ids->get('sales-channel')));

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->get('token'));

        $this->browser
            ->request(
                'GET',
                '/store-api/checkout/cart',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(10, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);
        static::assertSame('Test', $response['lineItems'][0]['label']);
    }
}
