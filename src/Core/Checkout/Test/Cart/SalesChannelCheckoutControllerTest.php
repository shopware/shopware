<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Rule\CartAmountRule;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Test\Payment\Handler\SyncTestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AssertArraySubsetBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\Response;

class SalesChannelCheckoutControllerTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour,
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

    /**
     * @var EntityRepositoryInterface
     */
    private $paymentMethodRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->currencyRepository = $this->getContainer()->get('currency.repository');
        $this->shippingMethodRepository = $this->getContainer()->get('shipping_method.repository');
        $this->paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
        $this->taxId = Uuid::randomHex();
        $this->manufacturerId = Uuid::randomHex();
        $this->context = Context::createDefaultContext();

        // reset rules
        $ruleLoader = $this->getContainer()->get(CartRuleLoader::class);
        $rulesProperty = ReflectionHelper::getProperty(CartRuleLoader::class, 'rules');
        $rulesProperty->setValue($ruleLoader, null);
    }

    public function testOrderProcess(): void
    {
        $productId = Uuid::randomHex();
        $productNumber = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => $productNumber,
                'stock' => 1,
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], $context);

        $addressId = Uuid::randomHex();

        $mail = Uuid::randomHex();
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
        $productId = Uuid::randomHex();
        $productNumber = Uuid::randomHex();
        $yen = [
            'id' => Uuid::randomHex(),
            'symbol' => '¥',
            'decimalPrecision' => 2,
            'factor' => 131.06,
            'shortName' => 'Yen',
            'name' => 'japanese Yen',
        ];
        $context = Context::createDefaultContext();
        $this->currencyRepository->create([$yen], $context);
        $salesChannelClient = $this->createCustomSalesChannelClient([
            'currencyId' => $yen['id'],
        ]);

        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => $productNumber,
                'stock' => 1,
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9, 'linked' => false],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], $context);

        $addressId = Uuid::randomHex();

        $mail = Uuid::randomHex();
        $password = 'shopware';

        $this->createCustomer($addressId, $mail, $password, $context);

        $client = $this->createCart($salesChannelClient);

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
        $productId = Uuid::randomHex();
        $productNumber = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $grossPrice = 10;
        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => $productNumber,
                'stock' => 1,
                'name' => 'Test',
                'price' => ['gross' => $grossPrice, 'net' => 9, 'linked' => false],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], $context);

        $mail = Uuid::randomHex() . '@shopware.unit';

        $firstName = 'Max';
        $lastName = 'Mustmann';
        $salutationId = $this->getValidSalutationId();

        $personal = [
            'email' => $mail,
            'salutationId' => $salutationId,
            'firstName' => $firstName,
            'lastName' => $lastName,
        ];

        $countryId = $this->getValidCountryId();
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

        $criteria = new Criteria([$order['orderCustomer']['customerId']]);
        $criteria->addAssociation('addresses');

        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search($criteria, $context)->first();

        static::assertEquals($firstName, $customer->getFirstName());
        static::assertEquals($lastName, $customer->getLastName());
        static::assertEquals($countryId, $order['addresses'][0]['country']['id'], print_r($order['addresses'], true));
        static::assertEquals($street, $order['addresses'][0]['street']);
        static::assertEquals($zipcode, $order['addresses'][0]['zipcode']);
        static::assertEquals($city, $order['addresses'][0]['city']);
        // todo@ju check shippingAddress when deliveries are implemented again
    }

    public function testGuestOrderProcessWithPayment(): void
    {
        // todo write test
        $productId = Uuid::randomHex();
        $productNumber = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $grossPrice = 10;
        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => $productNumber,
                'stock' => 1,
                'name' => 'Test',
                'price' => ['gross' => $grossPrice, 'net' => 9, 'linked' => false],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], $context);

        $mail = Uuid::randomHex() . '@shopware.unit';

        $firstName = 'Max';
        $lastName = 'Mustmann';
        $salutationId = $this->getValidSalutationId();

        $personal = [
            'email' => $mail,
            'salutationId' => $salutationId,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'billingAddress' => [
                'countryId' => $this->getValidCountryId(),
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

        $criteria = new Criteria([$order['orderCustomer']['customerId']]);
        $criteria->addAssociation('addresses');

        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search($criteria, $context)->first();

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
        $productId = Uuid::randomHex();
        $productNumber = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $grossPrice = 10;
        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => $productNumber,
                'stock' => 1,
                'name' => 'Test',
                'price' => ['gross' => $grossPrice, 'net' => 9, 'linked' => false],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], $context);

        $addressId = Uuid::randomHex();
        $mail = Uuid::randomHex() . '@shopware.unit';
        $password = 'shopware';

        $this->createCustomer($addressId, $mail, $password, $context);

        $personal = [
            'email' => $mail,
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'billingAddress' => [
                'countryId' => $this->getValidCountryId(),
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
        $productId = Uuid::randomHex();
        $productNumber = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $grossPrice = 10;
        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => $productNumber,
                'stock' => 1,
                'name' => 'Test',
                'price' => ['gross' => $grossPrice, 'net' => 9, 'linked' => false],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], $context);

        $guestMail = Uuid::randomHex() . '@shopware.unit';

        $firstName = 'Max';
        $lastName = 'Mustmann';
        $salutationId = $this->getValidSalutationId();

        $personal = [
            'email' => $guestMail,
            'salutationId' => $salutationId,
            'firstName' => $firstName,
            'lastName' => $lastName,
        ];

        $countryId = $this->getValidCountryId();
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

        $addressId = Uuid::randomHex();
        $mail = Uuid::randomHex();
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

        $criteria = new Criteria([$order['orderCustomer']['customerId']]);
        $criteria->addAssociation('addresses');

        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search($criteria, $context)->first();

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
        $addressId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $mail = Uuid::randomHex();
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
        $this->getSalesChannelClient()->setServerParameter($accessHeader, '');

        $orderId = $expectedOrder['data']['id'];
        $accessCode = $expectedOrder['data']['deepLinkCode'];
        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/checkout/guest-order/' . $orderId, ['accessCode' => $accessCode]);
        $response = $this->getSalesChannelClient()->getResponse();
        static::assertSame(200, $response->getStatusCode(), print_r($response, true));

        $actualOrder = json_decode($response->getContent(), true);
        $this->silentAssertArraySubset($expectedOrder, $actualOrder);
    }

    public function testDeepLinkGuestOrderWithAccessKey(): void
    {
        $expectedOrder = $this->createGuestOrder();
        $orderId = $expectedOrder['data']['id'];
        $accessCode = $expectedOrder['data']['deepLinkCode'];
        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/checkout/guest-order/' . $orderId, ['accessCode' => $accessCode]);

        $response = $this->getSalesChannelClient()->getResponse();
        static::assertSame(200, $response->getStatusCode());

        $actualOrder = json_decode($response->getContent(), true);
        $this->silentAssertArraySubset($expectedOrder, $actualOrder);
    }

    public function testDeepLinkGuestOrderWithWrongCode(): void
    {
        $order = $this->createGuestOrder();

        $accessHeader = 'HTTP_' . str_replace('-', '_', strtoupper(PlatformRequest::HEADER_ACCESS_KEY));
        $this->getSalesChannelClient()->setServerParameter($accessHeader, '');

        $orderId = $order['data']['id'];
        $accessCode = Random::getBase64UrlString(32);
        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/checkout/guest-order/' . $orderId, ['accessCode' => $accessCode]);

        $response = $this->getSalesChannelClient()->getResponse();
        static::assertSame(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        static::assertEquals(sprintf('Order with id "%s" not found.', $orderId), $content['errors'][0]['detail']);
    }

    public function testDeepLinkGuestOrderWithoutCode(): void
    {
        $order = $this->createGuestOrder();

        $accessHeader = 'HTTP_' . str_replace('-', '_', strtoupper(PlatformRequest::HEADER_ACCESS_KEY));
        $this->getSalesChannelClient()->setServerParameter($accessHeader, '');

        $orderId = $order['data']['id'];
        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/checkout/guest-order/' . $orderId);

        $response = $this->getSalesChannelClient()->getResponse();
        static::assertSame(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        static::assertEquals(sprintf('Order with id "%s" not found.', $orderId), $content['errors'][0]['detail']);
    }

    public function testDeepLinkGuestOrderWithWrongOrderId(): void
    {
        $order = $this->createGuestOrder();

        $accessHeader = 'HTTP_' . str_replace('-', '_', strtoupper(PlatformRequest::HEADER_ACCESS_KEY));
        $this->getSalesChannelClient()->setServerParameter($accessHeader, '');

        $orderId = Uuid::randomHex();
        $accessCode = $order['data']['deepLinkCode'];
        $this->getSalesChannelClient()->request('GET', '/sales-channel-api/v1/checkout/guest-order/' . $orderId, ['accessCode' => $accessCode]);

        $response = $this->getSalesChannelClient()->getResponse();
        static::assertSame(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        static::assertEquals(sprintf('Order with id "%s" not found.', $orderId), $content['errors'][0]['detail']);
    }

    public function testUnavailableShippingMethodIsBlock(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->productRepository->create([
            [
                'id' => $productId,
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'stock' => 42,
                'productNumber' => Uuid::randomHex(),
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], $context);

        $availableShippingMethodId = $this->getAvailableShippingMethodId();

        $unavailableShippingMethodId = Uuid::randomHex();

        $this->shippingMethodRepository->create([
            [
                'id' => $unavailableShippingMethodId,
                'name' => 'Unavailable',
                'calculation' => 0,
                'bindShippingfree' => false,
                'deliveryTime' => [
                    'name' => 'test',
                    'min' => 1,
                    'max' => 1,
                    'unit' => 'seconds',
                ],
                'prices' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'calculation' => 0,
                        'quantityStart' => 0,
                        'price' => 0,
                    ],
                ],
                'availabilityRule' => [
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
        ], $context);

        $ruleLoader = $this->getContainer()->get(CartRuleLoader::class);
        $rulesProperty = ReflectionHelper::getProperty(CartRuleLoader::class, 'rules');
        $rulesProperty->setValue($ruleLoader, null);

        $client = $this->createCart();
        $this->setShippingMethod($availableShippingMethodId, $client);
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

    public function testUnavailablePaymentMethodIsBlock(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->productRepository->create([
            [
                'id' => $productId,
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'stock' => 42,
                'productNumber' => Uuid::randomHex(),
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], $context);

        $unavailablePaymentMethodId = Uuid::randomHex();

        $this->paymentMethodRepository->create([
            [
                'id' => $unavailablePaymentMethodId,
                'handlerIdentifier' => SyncTestPaymentHandler::class,
                'name' => 'Unavailable',
                'position' => 0,
                'active' => true,
                'availabilityRule' => [
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
        ], $context);

        $ruleLoader = $this->getContainer()->get(CartRuleLoader::class);
        $rulesProperty = ReflectionHelper::getProperty(CartRuleLoader::class, 'rules');
        $rulesProperty->setValue($ruleLoader, null);

        $client = $this->createCart();
        $this->addProduct($client, $productId);
        $content = json_decode($client->getResponse()->getContent(), true);
        static::assertArrayHasKey('errors', $content['data']);
        static::assertCount(0, $content['data']['errors']);

        $this->setPaymentMethod($unavailablePaymentMethodId, $client);
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
        $productId = Uuid::randomHex();
        $productNumber = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $grossPrice = 10;
        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => $productNumber,
                'stock' => 1,
                'name' => 'Test',
                'price' => ['gross' => $grossPrice, 'net' => 9, 'linked' => false],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], $context);

        $mail = Uuid::randomHex() . '@shopware.unit';

        $firstName = 'Max';
        $lastName = 'Mustmann';
        $salutationId = $this->getValidSalutationId();

        $personal = [
            'email' => $mail,
            'salutationId' => $salutationId,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'billingAddress' => [
                'countryId' => $this->getValidCountryId(),
                'street' => 'Examplestreet 11',
                'zipcode' => '48411',
                'city' => 'Cologne',
            ],
        ];

        $client = $this->createCart();

        $quantity = 5;
        $this->addProduct($client, $productId, $quantity);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $ruleLoader = $this->getContainer()->get(CartRuleLoader::class);
        $rulesProperty = ReflectionHelper::getProperty(CartRuleLoader::class, 'rules');
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
                'salesChannelId' => $context->getSalesChannelId(),
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'not',
                    'lastName' => 'not',
                    'street' => 'test',
                    'city' => 'not',
                    'zipcode' => 'not',
                    'salutationId' => $this->getValidSalutationId(),
                    'country' => ['name' => 'not'],
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'test',
                    'description' => 'test',
                    'handlerIdentifier' => SyncTestPaymentHandler::class,
                    'availabilityRule' => [
                        'id' => Uuid::randomHex(),
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'cartCartAmount',
                                'value' => [
                                    'operator' => '>=',
                                    'amount' => 0,
                                ],
                            ],
                        ],
                    ],
                ],
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $mail,
                'password' => $password,
                'lastName' => 'not',
                'firstName' => 'match',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => 'not',
            ],
        ], $context);
    }

    private function createCart(?Client $client = null): Client
    {
        $salesChannelClient = $client;
        if ($client === null) {
            $salesChannelClient = $this->getSalesChannelClient();
        }
        $salesChannelClient->request('POST', '/sales-channel-api/v1/checkout/cart');
        $response = $salesChannelClient->getResponse();

        static::assertEquals(200, $response->getStatusCode(), $response->getContent());

        $content = json_decode($response->getContent(), true);

        if ($client === null) {
            $client = clone $salesChannelClient;
        }
        $client->setServerParameter('HTTP_X_SW_CONTEXT_TOKEN', $content[PlatformRequest::HEADER_CONTEXT_TOKEN]);

        return $client;
    }

    private function addProduct(Client $client, string $id, int $quantity = 1): void
    {
        $client->request(
            'POST',
            '/sales-channel-api/v1/checkout/cart/product/' . $id,
            [
                'quantity' => $quantity,
            ]
        );
    }

    private function order(Client $client): void
    {
        $client->request('POST', '/sales-channel-api/v1/checkout/order');
    }

    private function guestOrder(Client $client, array $payload): void
    {
        $client->request('POST', '/sales-channel-api/v1/checkout/guest-order', $payload);
    }

    private function login(Client $client, string $email, string $password): void
    {
        $client->request('POST', '/sales-channel-api/v1/customer/login', [
            'username' => $email,
            'password' => $password,
        ]);
        $response = $client->getResponse();
        $content = json_decode($response->getContent(), true);

        $client->setServerParameter('HTTP_X_SW_CONTEXT_TOKEN', $content[PlatformRequest::HEADER_CONTEXT_TOKEN]);
    }

    private function setShippingMethod(string $shippingId, Client $client): void
    {
        $client->request('PATCH', '/sales-channel-api/v1/context', [
            'shippingMethodId' => $shippingId,
        ]);
    }

    private function setPaymentMethod(string $paymentMethodId, Client $client): void
    {
        $client->request('PATCH', '/sales-channel-api/v1/context', [
            'paymentMethodId' => $paymentMethodId,
        ]);
    }
}
