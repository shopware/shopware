<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCompressor;
use Shopware\Core\Checkout\Cart\CartSerializationCleaner;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Checkout\Cart\RedisCartPersister;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Adapter\Cache\RedisConnectionFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

class FailingTestRedisCartPersisterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private string $salesChannelId;

    private RedisCartPersister $persister;

    /**
     * @var \Redis
     */
    private $redis;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelId = TestDefaults::SALES_CHANNEL;
        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->salesChannelId,
        ]);
        $addressId = Uuid::randomHex();
        $this->createCustomer(
            'customer@example.com',
            false,
            [
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'boundSalesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'via serravalle',
                    'city' => 'Oderzo',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
            ]
        );

        $factory = new RedisConnectionFactory();

        $client = $factory->create('redis://127.0.0.1:6379/3?persistent=1');
        static::assertInstanceOf(\Redis::class, $client);
        $this->redis = $client;
        $this->redis->flushall();
        $this->persister = new RedisCartPersister($this->redis, new CollectingEventDispatcher(), $this->createMock(CartSerializationCleaner::class), new CartCompressor(false, 'gzip'), 30);
    }

    public function testRedisCartPersister(): void
    {
        $this->browser
            ->request(
                'POST',
                '/account/login',
                [
                    'email' => 'customer@example.com',
                    'password' => 'shopware',
                ]
            );

        $session = $this->getSession();
        $contextToken = $session->get('sw-context-token');
        $params = new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $contextToken);

        $salesChannelContext = self::getContainer()->get(SalesChannelContextService::class)->get($params);

        $cartService = $this->getContainer()->get(CartService::class);
        $cart = $cartService->getCart($contextToken, $salesChannelContext);

        $productId = $this->createProduct($salesChannelContext, 'PRODUCT-0');

        $this->addProductToCart($productId, 10, $cart, $cartService, $salesChannelContext);

        $this->browser->request(
            'POST',
            '/checkout/product/add-by-number',
            [
                'number' => 'PRODUCT-0',
            ]
        );

        $this->persister->save($cart, $salesChannelContext);

        $cart = $this->persister->load($cart->getToken(), $salesChannelContext);

        $this->browser
            ->request(
                'GET',
                '/account/logout',
            );

        $this->browser
            ->request(
                'POST',
                '/account/login',
                [
                    'email' => 'customer@example.com',
                    'password' => 'shopware',
                ]
            );

        $session = $this->getSession();
        $contextToken = $session->get('sw-context-token');
        $params = new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $contextToken);
        $salesChannelContext = self::getContainer()->get(SalesChannelContextService::class)->get($params);

        $loaded = $this->persister->load($contextToken, $salesChannelContext);

        static::assertEquals($cart->getToken(), $loaded->getToken());
        static::assertEquals($cart->getLineItems(), $loaded->getLineItems());
    }

    private function createProduct(SalesChannelContext $context, ?string $productNumber = null, ?string $salesChannelId = null): string
    {
        $ids = new IdsCollection();
        $taxIds = $context->getTaxRules()->getIds();
        $ids->set('t1', (string) array_pop($taxIds));
        $product = (new ProductBuilder($ids, $productNumber ?? Uuid::randomHex()))
            ->price(1.0)
            ->tax('t1', 22)
            ->visibility($salesChannelId ?? TestDefaults::SALES_CHANNEL)
            ->build();

        self::getContainer()->get('product.repository')->create([$product], Context::createCLIContext());

        return $product['id'];
    }

    private function addProductToCart(string $productId, int $quantity, Cart $cart, CartService $cartService, SalesChannelContext $context): Cart
    {
        $factory = new ProductLineItemFactory(new PriceDefinitionFactory());
        $lineItem = $factory->create(['id' => $productId, 'referencedId' => $productId, 'quantity' => $quantity], $context);

        $cartService->add($cart, $lineItem, $context);
        $cartService->recalculate($cart, $context);

        return $cart;
    }
}
