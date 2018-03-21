<?php


namespace Shopware\Storefront\Test;


use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Customer\Definition\CustomerDefinition;
use Shopware\Api\Entity\Write\EntityWriter;
use Shopware\Api\Entity\Write\WriteContext;
use Shopware\Api\Order\Repository\OrderRepository;
use Shopware\Context\Struct\ShopContext;
use Shopware\Defaults;
use Shopware\Framework\Util\Random;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Client;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Response;

class OrderingProcessTest extends WebTestCase
{

    /**
     * @var Container
     */
    private static $container;

    /**
     * @var Client
     */
    private static $apiClient;

    /**
     * @var array
     */
    private static $apiUsernames;

    /**
     * @var Client
     */
    private static $webClient;

    /**
     * @var ShopContext
     */
    private static $context;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        $apiClient = self::createClient(
            ['test_case' => 'ApiTest'],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => ['application/vnd.api+json,application/json'],
            ]
        );

        self::$webClient = self::createClient(
            ['test_case' => 'OrderingProcessTest'],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest']
        );
        self::$container = self::$kernel->getContainer();
        self::$apiClient = self::authorizeClient($apiClient);
        self::$context = ShopContext::createDefaultContext();
    }

    public static function tearDownAfterClass()
    {
        self::$container->get(Connection::class)->executeQuery('DELETE FROM user WHERE username IN (:usernames)', ['usernames' => self::$apiUsernames], ['usernames' => Connection::PARAM_STR_ARRAY]);

        parent::tearDownAfterClass();
    }

    public function testOrderingProcess()
    {
        $email = Uuid::uuid4()->toString() . '@shopware.com';
        $customerId = $this->createCustomer($email, 'test1234');
        $this->loginUser($email, 'test1234');

        $product1 = $this->createProduct('Shopware stickers', 10, 11.9, 19);
        $product2 = $this->createProduct('Shopware t-shirt', 20, 23.8, 19);
        $product3 = $this->createProduct('Shopware cup', 5, 5.95, 19);

        $this->addProductToCart($product1, 1);
        $this->addProductToCart($product2, 5);
        $this->addProductToCart($product3, 10);

        $this->changeProductQuantity($product3, 3);

        $this->removeProductFromCart($product2);

        $this->changePaymentMethod(Defaults::PAYMENT_METHOD_PAID_IN_ADVANCE);

        $orderId = $this->payOrder();

        self::assertTrue(Uuid::isValid($orderId));

        $order = self::$container->get(OrderRepository::class)->readBasic([$orderId], self::$context)->get($orderId);

        self::assertEquals(Defaults::PAYMENT_METHOD_PAID_IN_ADVANCE, $order->getPaymentMethodId());
        self::assertEquals(25, $order->getAmountTotal());
        self::assertEquals($customerId, $order->getCustomer()->getId());

    }

    private static function authorizeClient(Client $client): Client
    {
        $username = Random::getAlphanumericString(30);
        $password = Random::getAlphanumericString(30);

        self::$container->get(Connection::class)->insert('user', [
            'id' => Uuid::uuid4()->getBytes(),
            'name' => $username,
            'email' => 'admin@example.com',
            'username' => $username,
            'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 13]),
            'locale_id' => Uuid::fromString('7b52d9dd-2b06-40ec-90be-9f57edf29be7')->getBytes(),
            'user_role_id' => '123',
            'active' => 1,
            'version_id' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes(),
            'locale_version_id' => Uuid::fromString(Defaults::LIVE_VERSION)->getBytes(),
        ]);

        self::$apiUsernames[] = $username;

        $authPayload = json_encode(['username' => $username, 'password' => $password]);

        $client->request('POST', '/api/auth', [], [], [], $authPayload);

        $data = json_decode($client->getResponse()->getContent(), true);

        $client->setServerParameter('HTTP_Authorization', sprintf('Bearer %s', $data['token']));

        return $client;
    }

    private function createProduct(
        string $name,
        float $grossPrice,
        float $netPrice,
        float $taxRate
    ): string {
        $id = Uuid::uuid4()->toString();

        $data = [
            'id' => $id,
            'name' => $name,
            'tax' => ['name' => 'test', 'rate' => $taxRate],
            'manufacturer' => ['name' => 'test'],
            'price' => ['gross' => $grossPrice, 'net' => $netPrice],
        ];

        $client = self::$apiClient;
        $client->request('POST', '/api/product', [], [], [], json_encode($data));
        $response = $client->getResponse();

        /* @var Response $response */
        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        self::assertNotEmpty($response->headers->get('Location'));
        self::assertStringEndsWith('api/product/' . $id, $response->headers->get('Location'));

        return $id;
    }

    private function addProductToCart(string $id, int $quantity)
    {
        $data = [
            'identifier' => $id,
            'quantity' => $quantity
        ];

        $client = self::$webClient;
        $client->request('POST', '/cart/addProduct', $data);
        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);

        self::assertEquals(true, $content['success']);
    }

    private function changeProductQuantity(string $id, int $quantity)
    {
        $data = [
            'identifier' => $id,
            'quantity' => $quantity,
        ];

        $client = self::$webClient;
        $client->request('POST', '/cart/setLineItemQuantity', $data);
        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);

        self::assertEquals(true, $content['success']);
    }

    private function removeProductFromCart(string $id)
    {
        $data = [
            'identifier' => $id,
        ];

        $client = self::$webClient;
        $client->request('POST', '/cart/removeLineItem', $data);
        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);

        self::assertEquals(true, $content['success']);
    }

    private function createCustomer($email, $password): string
    {
        $customerId = Uuid::uuid4()->toString();
        $addressId = Uuid::uuid4()->toString();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutation' => 'Herr',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 13]),
            'defaultPaymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'shopId' => Defaults::SHOP,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => 'ffe61e1c-9915-4f95-9701-4a310ab5482d',
                    'salutation' => 'Herr',
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];
        self::$container->get(EntityWriter::class)->upsert(CustomerDefinition::class, [$customer], $this->getContext());
        return $customerId;
    }

    private function loginUser(string $email, string $password)
    {
        $data = [
            'email' => $email,
            'password' => $password,
        ];

        $client = self::$webClient;
        $client->request('POST', '/account/login', $data);
        /** @var Response $response */
        $response = $client->getResponse();

        $this->assertStringEndsWith('/account', $response->headers->get('Location'));
    }

    private function changePaymentMethod(string $paymentMethodId)
    {
        $data = [
            'paymentMethodId' => $paymentMethodId,
        ];

        $client = self::$webClient;
        $client->request('POST', '/checkout/saveShippingPayment', $data);

        /** @var Response $response */
        $response = $client->getResponse();
        $this->assertStringEndsWith('/checkout/confirm', $response->headers->get('Location'));
    }

    private function payOrder(): string
    {
        $data = [
            'sAGB' => 'on',
        ];

        $client = self::$webClient;
        $client->request('POST', '/checkout/pay', $data);

        /** @var Response $response */
        $response = $client->getResponse();
        return $this->getOrderIdByResponse($response);
    }

    private function getOrderIdByResponse(Response $response): string
    {
        $location = $response->headers->get('Location');
        $query = parse_url($location, PHP_URL_QUERY);
        $parsedQuery = [];
        parse_str($query, $parsedQuery);

        return $parsedQuery['order'];
    }

    private function getContext()
    {
        return WriteContext::createFromShopContext(self::$context);
    }

}