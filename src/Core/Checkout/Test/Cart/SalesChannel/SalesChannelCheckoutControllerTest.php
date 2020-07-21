<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class SalesChannelCheckoutControllerTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;
    use AssertArraySubsetBehaviour;

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

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->currencyRepository = $this->getContainer()->get('currency.repository');
        $this->taxId = Uuid::randomHex();
        $this->manufacturerId = Uuid::randomHex();

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

        $browser = $this->createCart();
        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => $productNumber,
                'stock' => 1,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $browser->getServerParameter('test-sales-channel-id'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $context);

        $addressId = Uuid::randomHex();

        $mail = Uuid::randomHex() . '@shopware.com';
        $password = 'shopware';

        $this->createCustomer($addressId, $mail, $password, $context);

        $this->addProduct($browser, $productId);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $this->login($browser, $mail, $password);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $this->order($browser);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $order = json_decode($browser->getResponse()->getContent(), true);
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
            'isoCode' => 'JPY',
            'name' => 'japanese Yen',
        ];
        $context = Context::createDefaultContext();
        $this->currencyRepository->create([$yen], $context);
        $salesChannelClient = $this->createCustomSalesChannelBrowser([
            'currencyId' => $yen['id'],
        ]);

        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => $productNumber,
                'stock' => 1,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $salesChannelClient->getServerParameter('test-sales-channel-id'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $context);

        $addressId = Uuid::randomHex();

        $mail = Uuid::randomHex() . '@shopware.com';
        $password = 'shopware';

        $this->createCustomer($addressId, $mail, $password, $context);

        $browser = $this->createCart($salesChannelClient);

        $this->addProduct($browser, $productId);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $this->login($browser, $mail, $password);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $this->order($browser);

        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $order = json_decode($browser->getResponse()->getContent(), true);
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

        $browser = $this->createCart();
        $grossPrice = 10;
        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => $productNumber,
                'stock' => 1,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $grossPrice, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $browser->getServerParameter('test-sales-channel-id'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $context);

        $mail = Uuid::randomHex() . '@shopware.unit';

        $firstName = 'Max';
        $lastName = 'Mustermann';
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

        $quantity = 5;
        $this->addProduct($browser, $productId, $quantity);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $this->guestOrder($browser, array_merge($personal, $billing));
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $order = json_decode($browser->getResponse()->getContent(), true);
        static::assertArrayHasKey('data', $order);
        static::assertArrayHasKey(PlatformRequest::HEADER_CONTEXT_TOKEN, $order);

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
        $browser = $this->createCart();

        $grossPrice = 10;
        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => $productNumber,
                'stock' => 1,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $grossPrice, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $browser->getServerParameter('test-sales-channel-id'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $context);

        $mail = Uuid::randomHex() . '@shopware.unit';

        $firstName = 'Max';
        $lastName = 'Mustermann';
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

        $quantity = 5;
        $this->addProduct($browser, $productId, $quantity);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $this->guestOrder($browser, $personal);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $order = json_decode($browser->getResponse()->getContent(), true);
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
        $browser = $this->createCart();
        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => $productNumber,
                'stock' => 1,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $grossPrice, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $browser->getServerParameter('test-sales-channel-id'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
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

        $quantity = 5;
        $this->addProduct($browser, $productId, $quantity);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $this->guestOrder($browser, $personal);
        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());
    }

    public function testGuestOrderProcessWithLoggedInCustomer(): void
    {
        $productId = Uuid::randomHex();
        $productNumber = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $browser = $this->createCart();

        $grossPrice = 10;
        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => $productNumber,
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $grossPrice, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $browser->getServerParameter('test-sales-channel-id'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $context);

        $guestMail = Uuid::randomHex() . '@shopware.unit';

        $firstName = 'Max';
        $lastName = 'Mustermann';
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

        $this->login($browser, $mail, $password);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $quantity = 5;
        $this->addProduct($browser, $productId, $quantity);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $this->guestOrder($browser, array_merge($personal, $billing));
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $order = json_decode($browser->getResponse()->getContent(), true);
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

        $browser = $this->createCart();

        $this->login($browser, $mail, $password);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $this->order($browser);
        static::assertSame(400, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $response = json_decode($browser->getResponse()->getContent(), true);
        static::assertArrayHasKey('errors', $response);

        static::assertArrayHasKey('CHECKOUT__CART_EMPTY', array_flip(array_column($response['errors'], 'code')));
    }

    public function testDeepLinkGuestOrderWithoutAccessKey(): void
    {
        $expectedOrder = $this->createGuestOrder();

        $accessHeader = 'HTTP_' . str_replace('-', '_', mb_strtoupper(PlatformRequest::HEADER_ACCESS_KEY));
        $this->getSalesChannelBrowser()->setServerParameter($accessHeader, '');

        $orderId = $expectedOrder['data']['id'];
        $accessCode = $expectedOrder['data']['deepLinkCode'];
        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/guest-order/' . $orderId, ['accessCode' => $accessCode]);
        $response = $this->getSalesChannelBrowser()->getResponse();
        static::assertSame(500, $response->getStatusCode(), print_r($response, true));
    }

    public function testDeepLinkGuestOrderWithAccessKey(): void
    {
        $expectedOrder = $this->createGuestOrder();
        $orderId = $expectedOrder['data']['id'];
        $accessCode = $expectedOrder['data']['deepLinkCode'];
        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/guest-order/' . $orderId, ['accessCode' => $accessCode]);

        $response = $this->getSalesChannelBrowser()->getResponse();
        static::assertSame(200, $response->getStatusCode());

        $actualOrder = json_decode($response->getContent(), true);

        $actualOrder = $actualOrder['data'];
        $expectedOrder = $expectedOrder['data'];

        static::assertSame($actualOrder['orderNumber'], $expectedOrder['orderNumber']);
        static::assertSame($actualOrder['currencyId'], $expectedOrder['currencyId']);
        static::assertSame($actualOrder['salesChannelId'], $expectedOrder['salesChannelId']);
        static::assertSame($actualOrder['amountTotal'], $expectedOrder['amountTotal']);
        static::assertSame($actualOrder['amountNet'], $expectedOrder['amountNet']);
        static::assertSame($actualOrder['positionPrice'], $expectedOrder['positionPrice']);
        static::assertSame($actualOrder['taxStatus'], $expectedOrder['taxStatus']);
    }

    public function testDeepLinkGuestOrderWithWrongCode(): void
    {
        $order = $this->createGuestOrder();

        $orderId = $order['data']['id'];
        $accessCode = Random::getBase64UrlString(32);
        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/guest-order/' . $orderId, ['accessCode' => $accessCode]);

        $response = $this->getSalesChannelBrowser()->getResponse();
        static::assertSame(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        static::assertEquals(sprintf('Order with id "%s" not found.', $orderId), $content['errors'][0]['detail']);
    }

    public function testDeepLinkGuestOrderWithoutCode(): void
    {
        $order = $this->createGuestOrder();

        $orderId = $order['data']['id'];
        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/guest-order/' . $orderId);

        $response = $this->getSalesChannelBrowser()->getResponse();
        static::assertSame(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        static::assertEquals(sprintf('Order with id "%s" not found.', $orderId), $content['errors'][0]['detail']);
    }

    public function testDeepLinkGuestOrderWithWrongOrderId(): void
    {
        $order = $this->createGuestOrder();

        $orderId = Uuid::randomHex();
        $accessCode = $order['data']['deepLinkCode'];
        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/guest-order/' . $orderId, ['accessCode' => $accessCode]);

        $response = $this->getSalesChannelBrowser()->getResponse();
        static::assertSame(400, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        static::assertEquals(sprintf('Order with id "%s" not found.', $orderId), $content['errors'][0]['detail']);
    }

    private function createGuestOrder()
    {
        $productId = Uuid::randomHex();
        $productNumber = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $grossPrice = 10;
        $browser = $this->createCart();

        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => $productNumber,
                'stock' => 1,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $grossPrice, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $browser->getServerParameter('test-sales-channel-id'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $context);

        $mail = Uuid::randomHex() . '@shopware.unit';

        $firstName = 'Max';
        $lastName = 'Mustermann';
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

        $quantity = 5;
        $this->addProduct($browser, $productId, $quantity);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $ruleLoader = $this->getContainer()->get(CartRuleLoader::class);
        $rulesProperty = ReflectionHelper::getProperty(CartRuleLoader::class, 'rules');
        $rulesProperty->setValue($ruleLoader, null);

        $this->guestOrder($browser, $personal);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $order = json_decode($browser->getResponse()->getContent(), true);

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
                'salesChannelId' => Defaults::SALES_CHANNEL,
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
                'defaultPaymentMethodId' => $this->getAvailablePaymentMethod()->getId(),
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

    private function createCart(?KernelBrowser $browser = null): KernelBrowser
    {
        $salesChannelClient = $browser ?? $this->getSalesChannelBrowser();
        $this->assignSalesChannelContext($salesChannelClient);
        $salesChannelClient->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/cart');
        $response = $salesChannelClient->getResponse();

        static::assertEquals(200, $response->getStatusCode(), $response->getContent());

        $content = json_decode($response->getContent(), true);

        if ($browser === null) {
            $browser = clone $salesChannelClient;
        }
        $browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $content[PlatformRequest::HEADER_CONTEXT_TOKEN]);

        return $browser;
    }

    private function addProduct(KernelBrowser $browser, string $id, int $quantity = 1): void
    {
        $browser->request(
            'POST',
            '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/cart/product/' . $id,
            [
                'quantity' => $quantity,
            ]
        );
    }

    private function order(KernelBrowser $browser): void
    {
        $browser->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/order');
    }

    private function guestOrder(KernelBrowser $browser, array $payload): void
    {
        $browser->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/guest-order', $payload);
    }

    private function login(KernelBrowser $browser, string $email, string $password): void
    {
        $browser->request('POST', '/sales-channel-api/v3/customer/login', [
            'username' => $email,
            'password' => $password,
        ]);
        $response = $browser->getResponse();
        $content = json_decode($response->getContent(), true);

        $browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $content[PlatformRequest::HEADER_CONTEXT_TOKEN]);
    }
}
