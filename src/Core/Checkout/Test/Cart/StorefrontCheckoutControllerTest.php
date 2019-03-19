<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Checkout\Cart\Rule\CartAmountRule;
use Shopware\Core\Checkout\Context\CheckoutRuleLoader;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AssertArraySubsetBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\StorefrontFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\Response;

class StorefrontCheckoutControllerTest extends TestCase
{
    use StorefrontFunctionalTestBehaviour,
        AssertArraySubsetBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $currencyRepository;

    /**
     * @var string
     */
    private $taxId;

    /**
     * @var string
     */
    private $manufacturerId;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var EntityRepositoryInterface
     */
    private $shippingMethodRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->currencyRepository = $this->getContainer()->get('currency.repository');
        $this->shippingMethodRepository = $this->getContainer()->get('shipping_method.repository');
        $this->taxId = Uuid::uuid4()->getHex();
        $this->manufacturerId = Uuid::uuid4()->getHex();
        $this->context = Context::createDefaultContext();
    }

    public function testOrderProcess(): void
    {
        $productId = Uuid::uuid4()->getHex();
        $context = Context::createDefaultContext();

        $this->productRepository->create([
            [
                'id' => $productId,
                'stock' => 1,
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], $context);

        $addressId = Uuid::uuid4()->getHex();

        $mail = Uuid::uuid4()->getHex();
        $password = 'shopware';

        $this->createCustomer($addressId, $mail, $password, $context);

        $client = $this->createCart();

        $this->addProduct($client, $productId);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->login($client, $mail, $password);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->order($client);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $order = json_decode($client->getResponse()->getContent(), true);
        static::assertArrayHasKey('data', $order);

        $order = $order['data'];
        static::assertNotEmpty($order);

        static::assertEquals($mail, $order['orderCustomer']['email']);
    }

    public function testOrderProcessWithDifferentCurrency(): void
    {
        $productId = Uuid::uuid4()->getHex();
        $yen = [
            'id' => Uuid::uuid4()->getHex(),
            'symbol' => '¥',
            'decimalPrecision' => 2,
            'factor' => 131.06,
            'shortName' => 'Yen',
            'name' => 'japanese Yen',
        ];
        $context = Context::createDefaultContext();
        $this->currencyRepository->create([$yen], $context);
        $yenStorefrontClient = $this->createCustomStorefrontClient([
            'currencyId' => $yen['id'],
        ]);

        $this->productRepository->create([
            [
                'id' => $productId,
                'stock' => 1,
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], $context);

        $addressId = Uuid::uuid4()->getHex();

        $mail = Uuid::uuid4()->getHex();
        $password = 'shopware';

        $this->createCustomer($addressId, $mail, $password, $context);

        $client = $this->createCart($yenStorefrontClient);

        $this->addProduct($client, $productId);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->login($client, $mail, $password);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->order($client);

        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $order = json_decode($client->getResponse()->getContent(), true);
        static::assertArrayHasKey('data', $order);

        $order = $order['data'];
        static::assertNotEmpty($order);

        static::assertEquals($mail, $order['orderCustomer']['email']);

        static::assertEquals($yen['factor'], $order['currencyFactor']);
    }

    public function testGuestOrderProcess(): void
    {
        $productId = Uuid::uuid4()->getHex();
        $context = Context::createDefaultContext();

        $grossPrice = 10;
        $this->productRepository->create([
            [
                'id' => $productId,
                'stock' => 1,
                'name' => 'Test',
                'price' => ['gross' => $grossPrice, 'net' => 9, 'linked' => false],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], $context);

        $mail = Uuid::uuid4()->getHex();

        $firstName = 'Max';
        $lastName = 'Mustmann';
        $salutationId = Defaults::SALUTATION_ID_MR;

        $personal = [
            'email' => $mail,
            'salutationId' => $salutationId,
            'firstName' => $firstName,
            'lastName' => $lastName,
        ];

        $countryId = Defaults::COUNTRY;
        $street = 'Examplestreet 11';
        $zipcode = '48441';
        $city = 'Cologne';

        $billing = [
            'billingAddress' => [
                'countryId' => $countryId,
                'street' => $street,
                'zipcode' => $zipcode,
                'city' => $city,
            ],
        ];

        $client = $this->createCart();

        $quantity = 5;
        $this->addProduct($client, $productId, $quantity);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->guestOrder($client, array_merge($personal, $billing));
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $order = json_decode($client->getResponse()->getContent(), true);
        static::assertArrayHasKey('data', $order);

        $order = $order['data'];
        static::assertNotEmpty($order);

        static::assertEquals($grossPrice * $quantity, $order['amountTotal']);
        static::assertEquals($mail, $order['orderCustomer']['email']);

        static::assertNotEmpty($order['orderCustomer']['customerId']);

        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search(new Criteria([$order['orderCustomer']['customerId']]), $context)->first();

        static::assertEquals($firstName, $customer->getFirstName());
        static::assertEquals($lastName, $customer->getLastName());
        static::assertEquals($countryId, $order['addresses'][0]['country']['id']);
        static::assertEquals($street, $order['addresses'][0]['street']);
        static::assertEquals($zipcode, $order['addresses'][0]['zipcode']);
        static::assertEquals($city, $order['addresses'][0]['city']);
        // todo@ju check shippingAddress when deliveries are implemented again
    }

    public function testGuestOrderProcessWithPayment(): void
    {
        // todo write test
        $productId = Uuid::uuid4()->getHex();
        $context = Context::createDefaultContext();

        $grossPrice = 10;
        $this->productRepository->create([
            [
                'id' => $productId,
                'stock' => 1,
                'name' => 'Test',
                'price' => ['gross' => $grossPrice, 'net' => 9, 'linked' => false],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], $context);

        $mail = Uuid::uuid4()->getHex();

        $firstName = 'Max';
        $lastName = 'Mustmann';
        $salutationId = Defaults::SALUTATION_ID_MR;

        $personal = [
            'email' => $mail,
            'salutationId' => $salutationId,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'billingAddress' => [
                'countryId' => Defaults::COUNTRY,
                'street' => 'Examplestreet 11',
                'zipcode' => '48441',
                'city' => 'Cologne',
            ],
        ];

        $client = $this->createCart();

        $quantity = 5;
        $this->addProduct($client, $productId, $quantity);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->guestOrder($client, $personal);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $order = json_decode($client->getResponse()->getContent(), true);
        static::assertArrayHasKey('data', $order);

        $order = $order['data'];
        static::assertNotEmpty($order);

        static::assertEquals($grossPrice * $quantity, $order['amountTotal']);
        static::assertEquals($mail, $order['orderCustomer']['email']);

        static::assertNotEmpty($order['orderCustomer']['customerId']);

        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search(new Criteria([$order['orderCustomer']['customerId']]), $context)->first();

        static::assertEquals($firstName, $customer->getFirstName());
        static::assertEquals($lastName, $customer->getLastName());
        static::assertEquals($personal['billingAddress']['countryId'], $order['addresses'][0]['country']['id']);
        static::assertEquals($personal['billingAddress']['street'], $order['addresses'][0]['street']);
        static::assertEquals($personal['billingAddress']['zipcode'], $order['addresses'][0]['zipcode']);
        static::assertEquals($personal['billingAddress']['city'], $order['addresses'][0]['city']);

        // todo@ju check shippingAddress when deliveries are implemented again
    }

    public function testGuestOrderProcessWithExistingCustomer(): void
    {
        $productId = Uuid::uuid4()->getHex();
        $context = Context::createDefaultContext();

        $grossPrice = 10;
        $this->productRepository->create([
            [
                'id' => $productId,
                'stock' => 1,
                'name' => 'Test',
                'price' => ['gross' => $grossPrice, 'net' => 9, 'linked' => false],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], $context);

        $addressId = Uuid::uuid4()->getHex();
        $mail = Uuid::uuid4()->getHex();
        $password = 'shopware';

        $this->createCustomer($addressId, $mail, $password, $context);

        $personal = [
            'email' => $mail,
            'salutationId' => Defaults::SALUTATION_ID_MR,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'billingAddress' => [
                'countryId' => Defaults::COUNTRY,
                'street' => 'Examplestreet 11',
                'zipcode' => '48441',
                'city' => 'Cologne',
            ],
        ];

        $client = $this->createCart();

        $quantity = 5;
        $this->addProduct($client, $productId, $quantity);
        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->guestOrder($client, $personal);
        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());
    }

    public function testGuestOrderProcessWithLoggedInCustomer(): void
    {
        $productId = Uuid::uuid4()->getHex();
        $context = Context::createDefaultContext();

        $grossPrice = 10;
        $this->productRepository->create([
            [
                'id' => $productId,
                'stock' => 1,
                'name' => 'Test',
                'price' => ['gross' => $grossPrice, 'net' => 9, 'linked' => false],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], $context);

        $guestMail = Uuid::uuid4()->getHex();

        $firstName = 'Max';
        $lastName = 'Mustmann';
        $salutationId = Defaults::SALUTATION_ID_MR;

        $personal = [
            'email' => $guestMail,
            'salutationId' => $salutationId,
            'firstName' => $firstName,
            'lastName' => $lastName,
        ];

        $countryId = Defaults::COUNTRY;
        $street = 'Examplestreet 11';
        $zipcode = '48441';
        $city = 'Cologne';

        $billing = [
            'billingAddress' => [
                'countryId' => $countryId,
                'street' => $street,
                'zipcode' => $zipcode,
                'city' => $city,
            ],
        ];

        $addressId = Uuid::uuid4()->getHex();
        $mail = Uuid::uuid4()->getHex();
        $password = 'shopware';

        $this->createCustomer($addressId, $mail, $password, $context);

        $client = $this->createCart();

        $this->login($client, $mail, $password);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $quantity = 5;
        $this->addProduct($client, $productId, $quantity);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->guestOrder($client, array_merge($personal, $billing));
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $order = json_decode($client->getResponse()->getContent(), true);
        static::assertArrayHasKey('data', $order);

        $order = $order['data'];
        static::assertNotEmpty($order);

        static::assertEquals($grossPrice * $quantity, $order['amountTotal']);
        static::assertEquals($guestMail, $order['orderCustomer']['email']);

        static::assertNotEmpty($order['orderCustomer']['customerId']);

        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search(new Criteria([$order['orderCustomer']['customerId']]), $context)->first();

        static::assertEquals($firstName, $customer->getFirstName());
        static::assertEquals($lastName, $customer->getLastName());
        static::assertEquals($countryId, $order['addresses'][0]['country']['id']);
        static::assertEquals($street, $order['addresses'][0]['street']);
        static::assertEquals($zipcode, $order['addresses'][0]['zipcode']);
        static::assertEquals($city, $order['addresses'][0]['city']);
        // todo@ju check shippingAddress when deliveries are implemented again
    }

    public function testOrderProcessWithEmptyCart(): void
    {
        $addressId = Uuid::uuid4()->getHex();
        $context = Context::createDefaultContext();

        $mail = Uuid::uuid4()->getHex();
        $password = 'shopware';

        $this->createCustomer($addressId, $mail, $password, $context);

        $client = $this->createCart();

        $this->login($client, $mail, $password);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->order($client);
        static::assertSame(400, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $response = json_decode($client->getResponse()->getContent(), true);
        static::assertArrayHasKey('errors', $response);

        static::assertTrue(array_key_exists('CHECKOUT__CART_EMPTY', array_flip(array_column($response['errors'], 'code'))));
    }

    public function testDeepLinkGuestOrderWithoutAccessKey(): void
    {
        $expectedOrder = $this->createGuestOrder();

        $accessHeader = 'HTTP_' . str_replace('-', '_', strtoupper(PlatformRequest::HEADER_ACCESS_KEY));
        $this->getStorefrontClient()->setServerParameter($accessHeader, '');

        $orderId = $expectedOrder['data']['id'];
        $accessCode = $expectedOrder['data']['deepLinkCode'];
        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/checkout/guest-order/' . $orderId, ['accessCode' => $accessCode]);
        $response = $this->getStorefrontClient()->getResponse();
        static::assertSame(200, $response->getStatusCode(), print_r($response, true));

        $actualOrder = json_decode($response->getContent(), true);
        $this->silentAssertArraySubset($expectedOrder, $actualOrder);
    }

    public function testDeepLinkGuestOrderWithAccessKey(): void
    {
        $expectedOrder = $this->createGuestOrder();
        $orderId = $expectedOrder['data']['id'];
        $accessCode = $expectedOrder['data']['deepLinkCode'];
        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/checkout/guest-order/' . $orderId, ['accessCode' => $accessCode]);

        $response = $this->getStorefrontClient()->getResponse();
        static::assertSame(200, $response->getStatusCode());

        $actualOrder = json_decode($response->getContent(), true);
        $this->silentAssertArraySubset($expectedOrder, $actualOrder);
    }

    public function testDeepLinkGuestOrderWithWrongCode(): void
    {
        $order = $this->createGuestOrder();

        $accessHeader = 'HTTP_' . str_replace('-', '_', strtoupper(PlatformRequest::HEADER_ACCESS_KEY));
        $this->getStorefrontClient()->setServerParameter($accessHeader, '');

        $orderId = $order['data']['id'];
        $accessCode = Random::getBase64UrlString(32);
        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/checkout/guest-order/' . $orderId, ['accessCode' => $accessCode]);

        $response = $this->getStorefrontClient()->getResponse();
        static::assertSame(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        static::assertEquals(sprintf('Order with id "%s" not found.', $orderId), $content['errors'][0]['detail']);
    }

    public function testDeepLinkGuestOrderWithoutCode(): void
    {
        $order = $this->createGuestOrder();

        $accessHeader = 'HTTP_' . str_replace('-', '_', strtoupper(PlatformRequest::HEADER_ACCESS_KEY));
        $this->getStorefrontClient()->setServerParameter($accessHeader, '');

        $orderId = $order['data']['id'];
        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/checkout/guest-order/' . $orderId);

        $response = $this->getStorefrontClient()->getResponse();
        static::assertSame(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        static::assertEquals(sprintf('Order with id "%s" not found.', $orderId), $content['errors'][0]['detail']);
    }

    public function testDeepLinkGuestOrderWithWrongOrderId(): void
    {
        $order = $this->createGuestOrder();

        $accessHeader = 'HTTP_' . str_replace('-', '_', strtoupper(PlatformRequest::HEADER_ACCESS_KEY));
        $this->getStorefrontClient()->setServerParameter($accessHeader, '');

        $orderId = Uuid::uuid4()->getHex();
        $accessCode = $order['data']['deepLinkCode'];
        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/checkout/guest-order/' . $orderId, ['accessCode' => $accessCode]);

        $response = $this->getStorefrontClient()->getResponse();
        static::assertSame(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        static::assertEquals(sprintf('Order with id "%s" not found.', $orderId), $content['errors'][0]['detail']);
    }

    public function testUnavailableShippingMethodIsBlock(): void
    {
        static::markTestSkipped('This tests needs the recalculation of the cart');
        $productId = Uuid::uuid4()->getHex();
        $context = Context::createDefaultContext();

        $this->productRepository->create([
            [
                'id' => $productId,
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], $context);

        $unavailableShippingMethodId = Uuid::uuid4()->getHex();

        $this->shippingMethodRepository->create([
            [
                'id' => $unavailableShippingMethodId,
                'name' => 'Unavailable',
                'calculation' => 0,
                'bindShippingfree' => false,
                'availabilityRules' => [
                    [
                        'name' => 'Cart > 50',
                        'priority' => 100,
                        'conditions' => [
                            [
                                'type' => (new CartAmountRule())->getName(),
                                'value' => [
                                    'amount' => 50,
                                    'operator' => '>',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], $context);

        $ruleLoader = $this->getContainer()->get(CheckoutRuleLoader::class);
        $rulesProperty = ReflectionHelper::getProperty(CheckoutRuleLoader::class, 'rules');
        $rulesProperty->setValue($ruleLoader, null);

        $client = $this->createCart();
        $this->addProduct($client, $productId);
        $content = json_decode($client->getResponse()->getContent(), true);
        static::assertArrayHasKey('errors', $content['data']);
        static::assertCount(0, $content['data']['errors']);

        $this->setShippingMethod($unavailableShippingMethodId, $client);
        $this->addProduct($client, $productId);
        $content = json_decode($client->getResponse()->getContent(), true);
        static::assertArrayHasKey('errors', $content['data']);
        static::assertCount(1, $content['data']['errors']);

        // add products with amount > 50
        $this->addProduct($client, $productId, 10);
        $content = json_decode($client->getResponse()->getContent(), true);
        static::assertArrayHasKey('errors', $content['data']);
        static::assertCount(0, $content['data']['errors']);
    }

    private function createGuestOrder()
    {
        $productId = Uuid::uuid4()->getHex();
        $context = Context::createDefaultContext();

        $grossPrice = 10;
        $this->productRepository->create([
            [
                'id' => $productId,
                'stock' => 1,
                'name' => 'Test',
                'price' => ['gross' => $grossPrice, 'net' => 9, 'linked' => false],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], $context);

        $mail = Uuid::uuid4()->getHex();

        $firstName = 'Max';
        $lastName = 'Mustmann';
        $salutationId = Defaults::SALUTATION_ID_MR;

        $personal = [
            'email' => $mail,
            'salutationId' => $salutationId,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'billingAddress' => [
                'countryId' => Defaults::COUNTRY,
                'street' => 'Examplestreet 11',
                'zipcode' => '48411',
                'city' => 'Cologne',
            ],
        ];

        $client = $this->createCart();

        $quantity = 5;
        $this->addProduct($client, $productId, $quantity);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $ruleLoader = $this->getContainer()->get(CheckoutRuleLoader::class);
        $rulesProperty = ReflectionHelper::getProperty(CheckoutRuleLoader::class, 'rules');
        $rulesProperty->setValue($ruleLoader, null);

        $this->guestOrder($client, $personal);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $order = json_decode($client->getResponse()->getContent(), true);

        static::assertNotEmpty($order['data']);

        return $order;
    }

    private function createCustomer(string $addressId, string $mail, string $password, Context $context): void
    {
        $this->connection->executeUpdate('DELETE FROM customer WHERE email = :mail', [
            'mail' => $mail,
        ]);

        $this->customerRepository->create([
            [
                'salesChannelId' => $context->getSourceContext()->getSalesChannelId(),
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'not',
                    'lastName' => 'not',
                    'street' => 'test',
                    'city' => 'not',
                    'zipcode' => 'not',
                    'salutationId' => Defaults::SALUTATION_ID_MR,
                    'country' => ['name' => 'not'],
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'test',
                    'additionalDescription' => 'test',
                    'technicalName' => Uuid::uuid4()->getHex(),
                ],
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $mail,
                'password' => $password,
                'lastName' => 'not',
                'firstName' => 'match',
                'salutationId' => Defaults::SALUTATION_ID_MR,
                'customerNumber' => 'not',
            ],
        ], $context);
    }

    private function createCart(?Client $client = null): Client
    {
        $storefrontClient = $client;
        if ($client === null) {
            $storefrontClient = $this->getStorefrontClient();
        }
        $storefrontClient->request('POST', '/storefront-api/v1/checkout/cart');
        $response = $storefrontClient->getResponse();

        static::assertEquals(200, $response->getStatusCode(), $response->getContent());

        $content = json_decode($response->getContent(), true);

        if ($client === null) {
            $client = clone $storefrontClient;
        }
        $client->setServerParameter('HTTP_X_SW_CONTEXT_TOKEN', $content[PlatformRequest::HEADER_CONTEXT_TOKEN]);

        return $client;
    }

    private function addProduct(Client $client, string $id, int $quantity = 1): void
    {
        $client->request(
            'POST',
            '/storefront-api/v1/checkout/cart/product/' . $id,
            [
                'quantity' => $quantity,
            ]
        );
    }

    private function order(Client $client): void
    {
        $client->request('POST', '/storefront-api/v1/checkout/order');
    }

    private function guestOrder(Client $client, array $payload): void
    {
        $client->request('POST', '/storefront-api/v1/checkout/guest-order', $payload);
    }

    private function login(Client $client, string $email, string $password): void
    {
        $client->request('POST', '/storefront-api/v1/customer/login', [
            'username' => $email,
            'password' => $password,
        ]);
    }

    private function setShippingMethod(string $shippingId, Client $client): void
    {
        $client->request('PATCH', '/storefront-api/v1/context', [
            'shippingMethodId' => $shippingId,
        ]);
    }
}
