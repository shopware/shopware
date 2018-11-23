<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Checkout\Order;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Enrichment;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

class OrderRecalculationTest extends TestCase
{
    use IntegrationTestBehaviour,
        AdminApiTestBehaviour;

    /**
     * @var OrderPersister
     */
    private $orderPersister;

    /**
     * @var CheckoutContextFactory
     */
    private $factory;

    /**
     * @var CheckoutContext
     */
    private $checkoutContext;

    /**
     * @var RepositoryInterface
     */
    private $customerRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var OrderConverter
     */
    private $orderConverter;

    /**
     * @var Enrichment
     */
    private $enrichment;

    /**
     * @var Processor
     */
    private $processor;

    /**
     * @var RepositoryInterface
     */
    private $orderRepository;

    protected function setUp()
    {
        parent::setUp();

        $this->orderPersister = $this->getContainer()->get(OrderPersister::class);
        $this->factory = $this->getContainer()->get(CheckoutContextFactory::class);
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->context = Context::createDefaultContext();
        $this->orderConverter = $this->getContainer()->get(OrderConverter::class);
        $this->enrichment = $this->getContainer()->get(Enrichment::class);
        $this->processor = $this->getContainer()->get(Processor::class);
        $this->orderRepository = $this->getContainer()->get('order.repository');

        $customerId = $this->createCustomer();
        $this->checkoutContext = $this->factory->create(
            Uuid::uuid4()->getHex(),
            Defaults::SALES_CHANNEL,
            [
                CheckoutContextService::CUSTOMER_ID => $customerId,
            ]);
    }

    public function testPersistOrderAndConvertToCart()
    {
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart);

        $order = $this->orderRepository->read(new ReadCriteria([$orderId]), $this->context)->get($orderId);
        $convertedCart = $this->orderConverter->convertToCart($order, $this->context);

        // check name and token
        self::assertEquals(OrderConverter::CART_TYPE, $convertedCart->getName());
        self::assertNotEquals($cart->getToken(), $convertedCart->getToken());
        self::assertTrue(Uuid::isValid($convertedCart->getToken()));

        // set name and token to be equal for further comparison
        $cart->setName($convertedCart->getName());
        $cart->setToken($convertedCart->getToken());

        // transactions are currently not supported so they are excluded for comparison
        $cart->setTransactions(new TransactionCollection());

        self::assertEquals($cart, $convertedCart, print_r(['original' => $cart, 'converted' => $convertedCart], true));
    }

    public function testOrderConverterController()
    {
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart);

        $client = $this->getClient();

        // transform order to cart
        $client->request(
            'POST',
            sprintf(
                '/api/v%s/order/actions/convertToCart/%s',
                PlatformRequest::API_VERSION,
                $orderId
            )
        );

        $response = $client->getResponse();
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $token = $content['token'];
        static::assertTrue(Uuid::isValid($token));

        // get cart over proxy
        $client->request(
            'GET',
            sprintf(
                '/api/v%s/proxy/storefront-api/%s/checkout/cart',
                PlatformRequest::API_VERSION,
                Defaults::SALES_CHANNEL
            ),
            [
                'token' => $token,
                'name' => OrderConverter::CART_TYPE,
            ]
        );

        $response = $client->getResponse();
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode($response->getContent(), true)['data'];

        static::assertEquals(62, $content['price']['netPrice']);
        static::assertEquals(62, $content['price']['totalPrice']);
        static::assertEquals(62, $content['price']['positionPrice']);
        static::assertEquals(CartPrice::TAX_STATE_GROSS, $content['price']['taxStatus']);
        static::assertCount(3, $content['lineItems']);

        // increase quantity of line item from 5 to 10
        $client->request(
            'PATCH',
            sprintf(
                '/api/v%s/proxy/storefront-api/%s/checkout/cart/line-item',
                PlatformRequest::API_VERSION,
                Defaults::SALES_CHANNEL
            ),
            [
                'id' => $cart->getLineItems()->first()->getKey(),
                'quantity' => 10,
                'token' => $token,
                'name' => OrderConverter::CART_TYPE,
            ]
        );

        $response = $client->getResponse();
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode($response->getContent(), true)['data'];

        static::assertEquals(112, $content['price']['netPrice']);
        static::assertEquals(112, $content['price']['totalPrice']);
        static::assertEquals(112, $content['price']['positionPrice']);
        static::assertCount(3, $content['lineItems']);
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::uuid4()->getHex();
        $addressId = Uuid::uuid4()->getHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutation' => 'Mr',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => Uuid::uuid4()->getHex() . '@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => Defaults::COUNTRY,
                    'salutation' => 'Mr',
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->customerRepository->upsert([$customer], $this->context);

        return $customerId;
    }

    private function generateDemoCart(): Cart
    {
        $cart = new Cart('A', 'a-b-c');
        $cart->add(
            (new LineItem('1', 'product_', 5))
                ->setPriceDefinition(new QuantityPriceDefinition(10, new TaxRuleCollection(), 5))
                ->setLabel('First product')
                ->setPayloadValue('id', '1')
                ->setStackable(true)
        );
        $cart->add(
            (new LineItem('2', 'custom_absolute', 1))
                ->setPriceDefinition(new AbsolutePriceDefinition(3))
                ->setLabel('Second custom line item with absolute price definition')
        );

        $cart->add(
            (new LineItem('abcdefg', 'nested', 1))
                ->setLabel('Third line item (multi level nested)')
                ->addChild(
                    (new LineItem('3-1', 'custom', 1))
                        ->setLabel('Custom child depth 1 of the third line item')
                        ->addChild(
                            (new LineItem('3-1-1', 'product_', 1))
                                ->setPriceDefinition(new QuantityPriceDefinition(9, new TaxRuleCollection(), 1))
                                ->setLabel('Product depth 2 of third line item')
                                ->setPayloadValue('id', '3-1-1')
                        )
                )
        );
        $cart = $this->enrichment->enrich($cart, $this->checkoutContext);
        $cart = $this->processor->process($cart, $this->checkoutContext);

        return $cart;
    }

    private function persistCart(Cart $cart): string
    {
        $events = $this->orderPersister->persist($cart, $this->checkoutContext);
        $orderIds = $events->getEventByDefinition(OrderDefinition::class)->getIds();

        if (count($orderIds) !== 1) {
            self::fail('Order could not be persisted');
        }

        return $orderIds[0];
    }
}
