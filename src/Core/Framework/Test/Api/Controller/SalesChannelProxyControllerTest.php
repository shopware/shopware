<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Test\Cart\Common\TrueRule;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionTestFixtureBehaviour;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\AssertArraySubsetBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

class SalesChannelProxyControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use AssertArraySubsetBehaviour;
    use PromotionTestFixtureBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    protected $promotionRepository;

    /**
     * @var string
     */
    private $taxId;

    /**
     * @var string
     */
    private $manufacturerId;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var SalesChannelContextPersister
     */
    private $contextPersister;

    protected function setUp(): void
    {
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->promotionRepository = $this->getContainer()->get('promotion.repository');
        $this->taxId = Uuid::randomHex();
        $this->manufacturerId = Uuid::randomHex();
        $this->context = Context::createDefaultContext();
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->contextPersister = new SalesChannelContextPersister($this->connection);
    }

    public function testProxyWithInvalidSalesChannelId(): void
    {
        $this->getBrowser()->request('GET', $this->getUrl(Uuid::randomHex(), '/product'));

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response, true);

        static::assertArrayHasKey('errors', $response);
        static::assertEquals('FRAMEWORK__INVALID_SALES_CHANNEL', $response['errors'][0]['code'] ?? null);
    }

    public function testProxyCallToSalesChannelApi(): void
    {
        $salesChannel = $this->createSalesChannel();

        $this->getBrowser()->request('GET', $this->getUrl($salesChannel['id'], '/product'));

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response, true);

        static::assertArrayNotHasKey('errors', $response);
    }

    public function testHeadersAreCopied(): void
    {
        $salesChannel = $this->createSalesChannel();
        $uuid = Uuid::randomHex();

        $this->getBrowser()->request(
            'GET',
            $this->getUrl($salesChannel['id'], '/product'),
            [],
            [],
            [
                'HTTP_SW_CONTEXT_TOKEN' => $uuid,
                'HTTP_SW_LANGUAGE_ID' => $uuid,
                'HTTP_SW_VERSION_ID' => $uuid,
            ]
        );

        static::assertEquals($uuid, $this->getBrowser()->getRequest()->headers->get('sw-context-token'));
        static::assertEquals($uuid, $this->getBrowser()->getRequest()->headers->get('sw-language-id'));
        static::assertEquals($uuid, $this->getBrowser()->getRequest()->headers->get('sw-version-id'));
        static::assertEquals($uuid, $this->getBrowser()->getResponse()->headers->get('sw-context-token'));
        static::assertEquals($uuid, $this->getBrowser()->getResponse()->headers->get('sw-language-id'));
        static::assertEquals($uuid, $this->getBrowser()->getResponse()->headers->get('sw-version-id'));
    }

    public function testOnlyDefinedHeadersAreCopied(): void
    {
        $salesChannel = $this->createSalesChannel();

        $this->getBrowser()->request(
            'GET',
            $this->getUrl($salesChannel['id'], '/product'),
            [],
            [],
            [
                'HTTP_SW_CUSTOM_HEADER' => 'foo',
            ]
        );

        static::assertEquals('foo', $this->getBrowser()->getRequest()->headers->get('sw-custom-header'));
        static::assertArrayNotHasKey('sw-custom-header', $this->getBrowser()->getResponse()->headers->all());
    }

    public function testDifferentLanguage(): void
    {
        $langId = Uuid::randomHex();
        $salesChannel = $this->createSalesChannel();
        $this->createLanguage($langId, $salesChannel['id']);

        $this->assertTranslation(
            ['name' => 'not translated', 'translated' => ['name' => 'not translated']],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'not translated'],
                    $langId => ['name' => 'translated'],
                ],
            ],
            $salesChannel['id'],
            Defaults::LANGUAGE_SYSTEM
        );

        $this->assertTranslation(
            ['name' => 'translated', 'translated' => ['name' => 'translated']],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'not translated'],
                    $langId => ['name' => 'translated'],
                ],
            ],
            $salesChannel['id'],
            $langId
        );

        $this->assertTranslation(
            ['name' => 'translated', 'translated' => ['name' => 'translated']],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'not translated'],
                    $langId => ['name' => 'translated'],
                ],
            ],
            $salesChannel['id'],
            $langId
        );
    }

    public function testUpdatingPromotionAfterUpdateProductLineItem(): void
    {
        $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $productId = Uuid::randomHex();
        $promotionCode = 'BF99';

        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $salesChannelContext);

        $browser = $this->createCart(Defaults::SALES_CHANNEL);

        $this->addProduct($browser, Defaults::SALES_CHANNEL, $productId);

        // Add promotion code to our cart (not existing in DB)
        $this->addPromotionCodeByAPI($browser, Defaults::SALES_CHANNEL, $promotionCode);

        // Save promotion to database
        $this->createTestFixturePercentagePromotion(Uuid::randomHex(), $promotionCode, 100, null, $this->getContainer());

        $cart = $this->getCart($browser, Defaults::SALES_CHANNEL);

        $this->updateLineItemQuantity($browser, Defaults::SALES_CHANNEL, $cart['lineItems'][0]['id'], 3);

        $cart = $this->getCart($browser, Defaults::SALES_CHANNEL);
        static::assertCount(2, $cart['lineItems']);
    }

    public function testSwitchCustomerWithoutSalesChannelId(): void
    {
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $customerId = $this->createCustomer($salesChannelContext, 'info@example.com', 'shopware');

        $this->getBrowser()->request('PATCH', $this->getRootProxyUrl('/switch-customer'), [
            'customerId' => $customerId,
        ]);

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response, true);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertEquals('FRAMEWORK__MISSING_REQUEST_PARAMETER', $response['errors'][0]['code'] ?? null);
    }

    public function testSwitchCustomerWithInvalidChannelId(): void
    {
        $salesChannelId = Uuid::randomHex();
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $customerId = $this->createCustomer($salesChannelContext, 'info@example.com', 'shopware');
        $this->getBrowser()->request('PATCH', $this->getRootProxyUrl('/switch-customer'), [
            'salesChannelId' => $salesChannelId,
            'customerId' => $customerId,
        ]);

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response, true);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertEquals('FRAMEWORK__INVALID_SALES_CHANNEL', $response['errors'][0]['code'] ?? null);
    }

    public function testSwitchCustomerWithoutCustomerId(): void
    {
        $salesChannel = $this->createSalesChannel();

        $this->getBrowser()->request('PATCH', $this->getRootProxyUrl('/switch-customer'), [
            'salesChannelId' => $salesChannel['id'],
        ]);

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response, true);

        static::assertArrayHasKey('errors', $response);
        static::assertEquals('FRAMEWORK__MISSING_REQUEST_PARAMETER', $response['errors'][0]['code'] ?? null);
    }

    public function testSwitchCustomerWithInvalidCustomerId(): void
    {
        $salesChannel = $this->createSalesChannel();

        $this->getBrowser()->request('PATCH', $this->getRootProxyUrl('/switch-customer'), [
            'salesChannelId' => $salesChannel['id'],
            'customerId' => Uuid::randomHex(),
        ]);

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response, true);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertSame('/customerId', $response['errors'][0]['source']['pointer']);
    }

    public function testSwitchCustomer(): void
    {
        $salesChannel = $this->createSalesChannel();

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $customerId = $this->createCustomer($salesChannelContext, 'info@example.com', 'shopware');

        $browser = $this->createCart($salesChannel['id']);

        $browser->request('PATCH', $this->getRootProxyUrl('/switch-customer'), [
            'salesChannelId' => $salesChannel['id'],
            'customerId' => $customerId,
        ]);

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response, true);
        $contextTokenHeaderName = $this->getContextTokenHeaderName();
        static::assertIsArray($response);
        static::assertArrayHasKey(PlatformRequest::HEADER_CONTEXT_TOKEN, $response);
        static::assertEquals($browser->getServerParameter($contextTokenHeaderName), $response[PlatformRequest::HEADER_CONTEXT_TOKEN]);

        //assert customer is updated in database
        $payload = $this->contextPersister->load($response[PlatformRequest::HEADER_CONTEXT_TOKEN]);
        static::assertIsArray($payload);
        static::assertArrayHasKey('customerId', $payload);
        static::assertEquals($customerId, $payload['customerId']);
    }

    public function testModifyShippingCostsWithoutChannelId(): void
    {
        $this->getBrowser()->request('PATCH', $this->getRootProxyUrl('/modify-shipping-costs'), [
            'shippingCosts' => [
                'unitPrice' => 20,
                'totalPrice' => 20,
            ],
        ]);

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response, true);

        static::assertArrayHasKey('errors', $response);
        static::assertEquals('FRAMEWORK__MISSING_REQUEST_PARAMETER', $response['errors'][0]['code'] ?? null);
    }

    public function testModifyShippingCostsWithoutShippingCosts(): void
    {
        $salesChannel = $this->createSalesChannel();
        $browser = $this->getBrowser();
        $browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', Uuid::randomHex());
        $browser->request('PATCH', $this->getRootProxyUrl('/modify-shipping-costs'), [
            'shippingCosts' => [],
            'salesChannelId' => $salesChannel['id'],
        ]);

        $response = $browser->getResponse()->getContent();
        $response = json_decode($response, true);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(2, $response['errors']);
        static::assertSame('/unitPrice', $response['errors'][0]['source']['pointer']);
        static::assertSame('/totalPrice', $response['errors'][1]['source']['pointer']);
    }

    public function testModifyShippingCostsWithInvalidShippingCosts(): void
    {
        $salesChannel = $this->createSalesChannel();
        $browser = $this->getBrowser();
        $browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', Uuid::randomHex());
        $browser->request('PATCH', $this->getRootProxyUrl('/modify-shipping-costs'), [
            'shippingCosts' => [
                'unitPrice' => 'not_numeric',
                'totalPrice' => -10,
            ],
            'salesChannelId' => $salesChannel['id'],
        ]);

        $response = $browser->getResponse()->getContent();
        $response = json_decode($response, true);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(2, $response['errors']);
        static::assertSame('/unitPrice', $response['errors'][0]['source']['pointer']);
        static::assertSame('/totalPrice', $response['errors'][1]['source']['pointer']);
    }

    public function testModifyShippingCosts(): void
    {
        $salesChannelContext = $this->createDefaultSalesChannelContext();
        $productId = Uuid::randomHex();
        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $salesChannelContext);

        $browser = $this->createCart(Defaults::SALES_CHANNEL);
        $this->addProduct($browser, Defaults::SALES_CHANNEL, $productId);

        $browser->request('PATCH', $this->getRootProxyUrl('/modify-shipping-costs'), [
            'shippingCosts' => [
                'unitPrice' => 20,
                'totalPrice' => 20,
            ],
            'salesChannelId' => Defaults::SALES_CHANNEL,
        ]);

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response, true);
        //assert response format
        static::assertNotEmpty($response);
        static::assertArrayHasKey('sw-context-token', $response);
        static::assertNotEmpty($response['sw-context-token']);

        $cart = $this->getCart($browser, Defaults::SALES_CHANNEL);

        //assert shipping costs in cart
        static::assertArrayHasKey('unitPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertEquals(20, $cart['deliveries'][0]['shippingCosts']['unitPrice']);

        static::assertArrayHasKey('totalPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertEquals(20, $cart['deliveries'][0]['shippingCosts']['totalPrice']);

        //create a new shipping method and request to change
        $shippingMethodId = $this->createShippingMethod();
        $browser->request('PATCH', $this->getUrl(Defaults::SALES_CHANNEL, '/context'), [
            'shippingMethodId' => $shippingMethodId,
        ]);

        //assert response format
        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response, true);
        static::assertNotEmpty($response);
        static::assertArrayHasKey('sw-context-token', $response);
        static::assertNotEmpty($response['sw-context-token']);

        $cart = $this->getCart($browser, Defaults::SALES_CHANNEL);

        //assert shipping method in cart is changed but shipping costs in cart is not changed
        static::assertArrayHasKey('name', $cart['deliveries'][0]['shippingMethod']);
        static::assertEquals('Test shipping method', $cart['deliveries'][0]['shippingMethod']['name']);

        static::assertArrayHasKey('unitPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertEquals(20, $cart['deliveries'][0]['shippingCosts']['unitPrice']);

        static::assertArrayHasKey('totalPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertEquals(20, $cart['deliveries'][0]['shippingCosts']['totalPrice']);
    }

    public function testSwitchDeliveryMethodAndPriceWillBeCalculated(): void
    {
        $salesChannelContext = $this->createDefaultSalesChannelContext();
        $productId = Uuid::randomHex();
        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $salesChannelContext);

        $browser = $this->createCart(Defaults::SALES_CHANNEL);
        $this->addProduct($browser, Defaults::SALES_CHANNEL, $productId);
        $cart = $this->getCart($browser, Defaults::SALES_CHANNEL);

        //assert shipping cost in cart is default from sales channel
        static::assertArrayHasKey('totalPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertEquals(0, $cart['deliveries'][0]['shippingCosts']['totalPrice']);

        //create a new shipping method and request to change
        $shippingMethodId = $this->createShippingMethod();
        $browser->request('PATCH', $this->getUrl(Defaults::SALES_CHANNEL, '/context'), [
            'shippingMethodId' => $shippingMethodId,
        ]);

        //assert response format
        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response, true);
        static::assertNotEmpty($response);
        static::assertArrayHasKey('sw-context-token', $response);
        static::assertNotEmpty($response['sw-context-token']);

        $cart = $this->getCart($browser, Defaults::SALES_CHANNEL);

        //assert shipping method and cost are changed
        static::assertArrayHasKey('name', $cart['deliveries'][0]['shippingMethod']);
        static::assertEquals('Test shipping method', $cart['deliveries'][0]['shippingMethod']['name']);

        static::assertArrayHasKey('totalPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertEquals(30, $cart['deliveries'][0]['shippingCosts']['totalPrice']);
    }

    public function testDisableAutomaticPromotions(): void
    {
        $salesChannelContext = $this->createDefaultSalesChannelContext();
        $this->createTestFixtureFixedDiscountPromotion(Uuid::randomHex(), 40, PromotionDiscountEntity::SCOPE_CART, null, $this->getContainer(), $salesChannelContext);

        $browser = $this->createCart(Defaults::SALES_CHANNEL);

        $productId = Uuid::randomHex();
        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $salesChannelContext);
        $this->addProduct($browser, Defaults::SALES_CHANNEL, $productId);

        //There are 2 line items in cart including 1 product and 1 automatic promotion
        $cart = $this->getCart($browser, Defaults::SALES_CHANNEL);
        static::assertCount(2, $cart['lineItems']);
        static::assertSame('product', $cart['lineItems'][0]['type']);
        static::assertSame('promotion', $cart['lineItems'][1]['type']);

        //Call to disable automatic promotions
        $browser->request('PATCH', $this->getRootProxyUrl('/disable-automatic-promotions'));
        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());

        //There is 1 line item in cart. It is product
        $cart = $this->getCart($browser, Defaults::SALES_CHANNEL);
        static::assertCount(1, $cart['lineItems']);
        static::assertNotSame('promotion', $cart['lineItems'][0]['type']);
    }

    public function testDisableAutomaticPromotionDoesNotAffectPromotionCodes(): void
    {
        $salesChannelContext = $this->createDefaultSalesChannelContext();
        $this->createTestFixtureFixedDiscountPromotion(Uuid::randomHex(), 40, PromotionDiscountEntity::SCOPE_CART, null, $this->getContainer(), $salesChannelContext);

        $browser = $this->createCart(Defaults::SALES_CHANNEL);

        $productId = Uuid::randomHex();
        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $salesChannelContext);
        $this->addProduct($browser, Defaults::SALES_CHANNEL, $productId);

        // Add promotion code into cart
        $promotionCode = Random::getAlphanumericString(5);
        $this->createTestFixtureAbsolutePromotion(Uuid::randomHex(), $promotionCode, 100, $this->getContainer());
        $browser->request('POST', $this->getUrl(Defaults::SALES_CHANNEL, 'checkout/cart/code/' . $promotionCode));

        // Check there are automatic promotion and promotion code in cart
        $cart = $this->getCart($browser, Defaults::SALES_CHANNEL);
        static::assertCount(3, $cart['lineItems']);
        static::assertSame('product', $cart['lineItems'][0]['type']);
        static::assertSame('promotion', $cart['lineItems'][1]['type']);
        static::assertSame('promotion', $cart['lineItems'][2]['type']);
        static::assertSame($promotionCode, $cart['lineItems'][2]['referencedId']);

        // Call to disable automatic promotion
        $browser->request('PATCH', $this->getRootProxyUrl('/disable-automatic-promotions'));
        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());

        // Check automatic promotion code is disabled and exist the promotion code in cart
        $cart = $this->getCart($browser, Defaults::SALES_CHANNEL);
        static::assertCount(2, $cart['lineItems']);
        static::assertSame($promotionCode, $cart['lineItems'][1]['referencedId']);
    }

    public function testEnableAutomaticPromotions(): void
    {
        $salesChannelContext = $this->createDefaultSalesChannelContext();
        $this->createTestFixtureFixedDiscountPromotion(Uuid::randomHex(), 40, PromotionDiscountEntity::SCOPE_CART, null, $this->getContainer(), $salesChannelContext);

        $browser = $this->createCart(Defaults::SALES_CHANNEL);

        $productId = Uuid::randomHex();
        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $salesChannelContext);
        $this->addProduct($browser, Defaults::SALES_CHANNEL, $productId);

        $cart = $this->getCart($browser, Defaults::SALES_CHANNEL);

        static::assertCount(2, $cart['lineItems']);
        static::assertSame('product', $cart['lineItems'][0]['type']);
        static::assertSame('promotion', $cart['lineItems'][1]['type']);

        // Call to disable automatic promotion
        $browser->request('PATCH', $this->getRootProxyUrl('/disable-automatic-promotions'));
        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());

        // Check automatic promotion is disabled
        $cart = $this->getCart($browser, Defaults::SALES_CHANNEL);
        static::assertCount(1, $cart['lineItems']);
        static::assertNotSame('promotion', $cart['lineItems'][0]['type']);

        // Call to enable automatic promotion
        $browser->request('PATCH', $this->getRootProxyUrl('/enable-automatic-promotions'));
        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());

        // Check automatic promotion is enabled
        $cart = $this->getCart($browser, Defaults::SALES_CHANNEL);
        static::assertCount(2, $cart['lineItems']);
        static::assertSame('product', $cart['lineItems'][0]['type']);
        static::assertSame('promotion', $cart['lineItems'][1]['type']);
    }

    private function getLangHeaderName(): string
    {
        return 'HTTP_' . mb_strtoupper(str_replace('-', '_', PlatformRequest::HEADER_LANGUAGE_ID));
    }

    private function assertTranslation(
        array $expectedTranslations,
        array $data,
        string $salesChannelId,
        ?string $langOverride = null
    ): void {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';

        $categoryData = $data;
        $categoryData['active'] = true;
        if (!isset($categoryData['id'])) {
            $categoryData['id'] = Uuid::randomHex();
        }

        $this->getBrowser()->request('POST', $baseResource, $categoryData);
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(204, $response->getStatusCode(), $response->getContent());

        $this->assertEntityExists($this->getBrowser(), 'category', $categoryData['id']);

        $headers = ['HTTP_ACCEPT' => 'application/json'];
        if ($langOverride) {
            $headers[$this->getLangHeaderName()] = $langOverride;
        }

        $this->getBrowser()->request('GET', $this->getUrl($salesChannelId, '/category/' . $categoryData['id']), [], [], $headers);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        $responseData = json_decode($response->getContent(), true);

        static::assertArrayHasKey('data', $responseData, $response->getContent());

        $this->silentAssertArraySubset($expectedTranslations, $responseData['data']);
    }

    private function createLanguage(string $langId, string $salesChannelId, $fallbackId = null): void
    {
        $baseUrl = '/api/v' . PlatformRequest::API_VERSION;

        if ($fallbackId) {
            $fallbackLocaleId = Uuid::randomHex();
            $parentLanguageData = [
                'id' => $fallbackId,
                'name' => 'test language ' . $fallbackId,
                'locale' => [
                    'id' => $fallbackLocaleId,
                    'code' => 'x-tst_' . $fallbackLocaleId,
                    'name' => 'Test locale ' . $fallbackLocaleId,
                    'territory' => 'Test territory ' . $fallbackLocaleId,
                ],
                'translationCodeId' => $fallbackLocaleId,
            ];
            $this->getBrowser()->request('POST', $baseUrl . '/language', $parentLanguageData);
            static::assertEquals(204, $this->getBrowser()->getResponse()->getStatusCode());
        }

        $localeId = Uuid::randomHex();
        $languageData = [
            'id' => $langId,
            'name' => 'test language ' . $langId,
            'parentId' => $fallbackId,
            'locale' => [
                'id' => $localeId,
                'code' => 'x-tst_' . $localeId,
                'name' => 'Test locale ' . $localeId,
                'territory' => 'Test territory ' . $localeId,
            ],
            'translationCodeId' => $localeId,
            'salesChannels' => [
                ['id' => $salesChannelId],
            ],
        ];

        $this->getBrowser()->request('POST', $baseUrl . '/language', $languageData);
        static::assertEquals(204, $this->getBrowser()->getResponse()->getStatusCode(), $this->getBrowser()->getResponse()->getContent());

        $this->getBrowser()->request('GET', $baseUrl . '/language/' . $langId);
    }

    private function getUrl(string $salesChannelId, string $url): string
    {
        return sprintf(
            '/api/v%d/_proxy/sales-channel-api/%s/v%1$d/%s',
            PlatformRequest::API_VERSION,
            $salesChannelId,
            ltrim($url, '/')
        );
    }

    private function getRootProxyUrl(string $url): string
    {
        return sprintf(
            '/api/v%d/_proxy/%s',
            PlatformRequest::API_VERSION,
            ltrim($url, '/')
        );
    }

    private function createSalesChannel(array $salesChannel = []): array
    {
        $defaults = [
            'id' => Uuid::randomHex(),
            'name' => 'unit test channel',
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $this->getValidCountryId(),
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
        ];

        $salesChannel = array_merge_recursive($defaults, $salesChannel);

        $this->salesChannelRepository->create([$salesChannel], Context::createDefaultContext());

        return $salesChannel;
    }

    private function createCart(string $saleChannelId): KernelBrowser
    {
        $this->getBrowser()->request('POST', $this->getUrl($saleChannelId, 'checkout/cart'));

        $response = $this->getBrowser()->getResponse();

        static::assertEquals(200, $response->getStatusCode(), $response->getContent());

        $content = json_decode($response->getContent(), true);

        $browser = clone $this->getBrowser();
        $browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $content[PlatformRequest::HEADER_CONTEXT_TOKEN]);

        return $browser;
    }

    private function addProduct(KernelBrowser $browser, string $salesChannelId, string $id, int $quantity = 1): void
    {
        $browser->request(
            'POST',
            $this->getUrl($salesChannelId, 'checkout/cart/product/' . $id),
            ['quantity' => $quantity]
        );
    }

    private function updateLineItemQuantity(
        KernelBrowser $browser,
        string $salesChannelId,
        string $lineItemId,
        int $quantity
    ): void {
        $browser->request(
            'PATCH',
            $this->getUrl($salesChannelId, 'checkout/cart/line-item/' . $lineItemId),
            ['quantity' => $quantity]
        );
    }

    private function getCart(KernelBrowser $browser, string $salesChannelId): array
    {
        $browser->request('GET', $this->getUrl($salesChannelId, 'checkout/cart'));

        $cart = json_decode($browser->getResponse()->getContent(), true);

        return $cart['data'] ?? $cart;
    }

    private function addPromotionCodeByAPI(KernelBrowser $browser, string $salesChannelId, string $code): void
    {
        $browser->request('POST', $this->getUrl($salesChannelId, 'checkout/cart/code/' . $code));
    }

    private function createCustomer(
        SalesChannelContext $salesChannelContext,
        string $email,
        string $password
    ): string {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $this->customerRepository->create([
            [
                'id' => $customerId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'country' => ['name' => 'Germany'],
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'Invoice',
                    'description' => 'Default payment method',
                ],
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => $password,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ], $salesChannelContext->getContext());

        return $customerId;
    }

    private function getContextTokenHeaderName(): string
    {
        return 'HTTP_' . mb_strtoupper(str_replace('-', '_', PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    private function createDefaultSalesChannelContext(): SalesChannelContext
    {
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        return $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
    }

    private function createShippingMethod(): string
    {
        $shippingMethodId = Uuid::randomHex();
        $repository = $this->getContainer()->get('shipping_method.repository');

        $ruleRegistry = $this->getContainer()->get(RuleConditionRegistry::class);
        $prop = ReflectionHelper::getProperty(RuleConditionRegistry::class, 'rules');
        $prop->setValue($ruleRegistry, array_merge($prop->getValue($ruleRegistry), ['true' => new TrueRule()]));

        $data = [
            'id' => $shippingMethodId,
            'type' => 0,
            'name' => 'Test shipping method',
            'bindShippingfree' => false,
            'active' => true,
            'prices' => [
                [
                    'name' => 'Std',
                    'price' => '10.00',
                    'currencyId' => Defaults::CURRENCY,
                    'calculation' => 1,
                    'quantityStart' => 1,
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 20,
                            'gross' => 30,
                            'linked' => false,
                        ],
                    ],
                ],
            ],
            'deliveryTime' => $this->createDeliveryTime(),
            'availabilityRule' => [
                'id' => Uuid::randomHex(),
                'name' => 'true',
                'priority' => 1,
                'conditions' => [
                    [
                        'type' => (new TrueRule())->getName(),
                    ],
                ],
            ],
        ];

        $repository->create([$data], $this->context);

        $saleChannelShippingMethodRepository = $this->getContainer()->get('sales_channel_shipping_method.repository');
        $saleChannelShippingMethodRepository->create([[
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'shippingMethodId' => $shippingMethodId,
        ]], $this->context);

        return $shippingMethodId;
    }

    private function createDeliveryTime(): array
    {
        return [
            'id' => Uuid::randomHex(),
            'name' => 'test',
            'min' => 1,
            'max' => 90,
            'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
        ];
    }
}
