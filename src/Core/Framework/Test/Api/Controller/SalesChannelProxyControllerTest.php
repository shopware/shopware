<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Checkout\Test\Cart\Common\TrueRule;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionTestFixtureBehaviour;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\EventListener\Acl\CreditOrderLineItemListener;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @group slow
 */
class SalesChannelProxyControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use PromotionTestFixtureBehaviour;

    /**
     * @var EntityRepository
     */
    protected $promotionRepository;

    private Context $context;

    /**
     * @var EntityRepository
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepository
     */
    private $customerRepository;

    /**
     * @var Connection
     */
    private $connection;

    private SalesChannelContextPersister $contextPersister;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $this->promotionRepository = $this->getContainer()->get('promotion.repository');
        $this->context = Context::createDefaultContext();
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $eventDispatcher = new EventDispatcher();
        $this->contextPersister = new SalesChannelContextPersister($this->connection, $eventDispatcher, $this->getContainer()->get(CartPersister::class));
        $this->ids = new TestDataCollection();
    }

    public function testProxyWithInvalidSalesChannelId(): void
    {
        $this->getBrowser()->request('GET', $this->getUrl(Uuid::randomHex(), '/product'));

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertEquals('FRAMEWORK__INVALID_SALES_CHANNEL', $response['errors'][0]['code'] ?? null);
    }

    public function testProxyCallToSalesChannelApi(): void
    {
        $salesChannel = $this->createSalesChannel();

        $this->getBrowser()->request('GET', $this->getUrl($salesChannel['id'], '/product'));

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response ?: '', true, 512, \JSON_THROW_ON_ERROR);

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
        $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $productId = Uuid::randomHex();
        $promotionCode = 'BF99';

        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $salesChannelContext);

        $browser = $this->createCart(TestDefaults::SALES_CHANNEL);

        $this->addProduct($browser, TestDefaults::SALES_CHANNEL, $productId);

        // Save promotion to database
        $this->createTestFixturePercentagePromotion(Uuid::randomHex(), $promotionCode, 100, null, $this->getContainer());

        // Add promotion code to our cart (not existing in DB)
        $this->addPromotionCodeByAPI($browser, TestDefaults::SALES_CHANNEL, $promotionCode);

        $cart = $this->getCart($browser, TestDefaults::SALES_CHANNEL);

        $this->updateLineItemQuantity($browser, TestDefaults::SALES_CHANNEL, $cart['lineItems'][0]['id'], 3);

        $cart = $this->getCart($browser, TestDefaults::SALES_CHANNEL);
        static::assertCount(2, $cart['lineItems']);
    }

    public function testSwitchCustomerWithoutSalesChannelId(): void
    {
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $customerId = $this->createCustomer($salesChannelContext, 'info@example.com', 'shopware');

        $this->getBrowser()->request('PATCH', $this->getRootProxyUrl('/switch-customer'), [
            'customerId' => $customerId,
        ]);

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertEquals('FRAMEWORK__MISSING_REQUEST_PARAMETER', $response['errors'][0]['code'] ?? null);
    }

    public function testSwitchCustomerWithInvalidChannelId(): void
    {
        $salesChannelId = Uuid::randomHex();
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $customerId = $this->createCustomer($salesChannelContext, 'info@example.com', 'shopware');
        $this->getBrowser()->request('PATCH', $this->getRootProxyUrl('/switch-customer'), [
            'salesChannelId' => $salesChannelId,
            'customerId' => $customerId,
        ]);

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response ?: '', true, 512, \JSON_THROW_ON_ERROR);

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
        $response = json_decode($response ?: '', true, 512, \JSON_THROW_ON_ERROR);

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
        $response = json_decode($response ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertSame('/customerId', $response['errors'][0]['source']['pointer']);
    }

    public function testSwitchCustomer(): void
    {
        $salesChannel = $this->createSalesChannel();

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $customerId = $this->createCustomer($salesChannelContext, 'info@example.com', 'shopware');

        $browser = $this->createCart($salesChannel['id']);

        $browser->request('PATCH', $this->getRootProxyUrl('/switch-customer'), [
            'salesChannelId' => $salesChannel['id'],
            'customerId' => $customerId,
        ]);

        $response = $this->getBrowser()->getResponse();

        $contextTokenHeaderName = $this->getContextTokenHeaderName();
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertEquals($browser->getServerParameter($contextTokenHeaderName), $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        static::assertIsString($salesChannel['id']);
        //assert customer is updated in database
        $payload = $this->contextPersister->load($response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN, ''), $salesChannel['id']);
        static::assertIsArray($payload);
        static::assertArrayHasKey('customerId', $payload);
        static::assertEquals($customerId, $payload['customerId']);
    }

    public function testSwitchCustomerWithPermissions(): void
    {
        $salesChannel = $this->createSalesChannel();
        static::assertIsString($salesChannel['id']);

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $customerId = $this->createCustomer($salesChannelContext, 'info@example.com', 'shopware');

        $permissions = [
            'allowProductPriceOverwrites',
            'allowProductLabelOverwrites',
            'skipProductRecalculation',
            'skipDeliveryPriceRecalculation',
            'skipDeliveryTaxRecalculation',
            'skipPromotion',
            'skipAutomaticPromotions',
            'skipProductStockValidation',
            'keepInactiveProduct',
        ];

        $browser = $this->createCart($salesChannel['id']);

        $browser->request('PATCH', $this->getRootProxyUrl('/switch-customer'), [
            'salesChannelId' => $salesChannel['id'],
            'customerId' => $customerId,
            'permissions' => $permissions,
        ]);

        $response = $this->getBrowser()->getResponse();

        //assert permissions exist in payload
        $payload = $this->contextPersister->load($response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN, ''), $salesChannel['id']);
        static::assertIsArray($payload);
        static::assertArrayHasKey('permissions', $payload);
        static::assertEqualsCanonicalizing(\array_fill_keys($permissions, true), $payload['permissions']);
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
        $response = json_decode($response ?: '', true, 512, \JSON_THROW_ON_ERROR);

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
        $response = json_decode($response ?: '', true, 512, \JSON_THROW_ON_ERROR);

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
        $response = json_decode($response ?: '', true, 512, \JSON_THROW_ON_ERROR);

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

        $browser = $this->createCart(TestDefaults::SALES_CHANNEL, $salesChannelContext->getToken());
        $this->addProduct($browser, TestDefaults::SALES_CHANNEL, $productId);

        $browser->request(
            'PATCH',
            $this->getRootProxyUrl('/modify-shipping-costs'),
            [],
            [],
            [],
            json_encode([
                'shippingCosts' => [
                    'unitPrice' => 20,
                    'totalPrice' => 20,
                ],
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
            ]) ?: ''
        );

        $response = $this->getBrowser()->getResponse();

        //assert response format
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNotEmpty($response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $cart = $this->getCart($browser, TestDefaults::SALES_CHANNEL);

        //assert shipping costs in cart
        static::assertArrayHasKey('unitPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertEquals(20, $cart['deliveries'][0]['shippingCosts']['unitPrice']);

        static::assertArrayHasKey('totalPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertEquals(20, $cart['deliveries'][0]['shippingCosts']['totalPrice']);

        //create a new shipping method and request to change
        $shippingMethodId = $this->createShippingMethod();

        $browser->request(
            'PATCH',
            $this->getUrl(TestDefaults::SALES_CHANNEL, '/context'),
            [],
            [],
            [],
            json_encode([
                'shippingMethodId' => $shippingMethodId,
            ], \JSON_THROW_ON_ERROR) ?: ''
        );

        //assert response format
        $response = $this->getBrowser()->getResponse();
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNotEmpty($response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $cart = $this->getCart($browser, TestDefaults::SALES_CHANNEL);

        //assert shipping method in cart is changed but shipping costs in cart is not changed
        static::assertArrayHasKey('name', $cart['deliveries'][0]['shippingMethod']);
        static::assertEquals('Test shipping method', $cart['deliveries'][0]['shippingMethod']['name']);

        static::assertArrayHasKey('unitPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertEquals(20, $cart['deliveries'][0]['shippingCosts']['unitPrice']);

        static::assertArrayHasKey('totalPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertEquals(20, $cart['deliveries'][0]['shippingCosts']['totalPrice']);
    }

    public function testModifyShippingWith0Costs(): void
    {
        $shippingMethodId = Uuid::randomHex();

        $this->getContainer()->get('shipping_method.repository')->create([
            [
                'id' => $shippingMethodId,
                'name' => 'Example shipping',
                'availabilityRule' => ['name' => 'test', 'priority' => 1],
                'deliveryTime' => ['name' => 'test', 'min' => 1, 'max' => 1, 'unit' => 'day'],
                'taxType' => ShippingMethodEntity::TAX_TYPE_AUTO,
                'prices' => [
                    [
                        'currencyPrice' => [
                            [
                                'currencyId' => Defaults::CURRENCY,
                                'gross' => 5,
                                'linked' => false,
                                'net' => 5,
                            ],
                        ],
                        'quantityStart' => 1,
                        'shippingMethodId' => $shippingMethodId,
                    ],
                ],
                'active' => true,
            ],
        ], $this->context);

        $this->salesChannelRepository->update([
            [
                'id' => TestDefaults::SALES_CHANNEL,
                'shippingMethodId' => $shippingMethodId,
            ],
        ], $this->context);

        $salesChannelContext = $this->createDefaultSalesChannelContext();

        $productId = Uuid::randomHex();
        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $salesChannelContext);

        $browser = $this->createCart(TestDefaults::SALES_CHANNEL, $salesChannelContext->getToken());
        $this->addProduct($browser, TestDefaults::SALES_CHANNEL, $productId);

        $cart = $this->getCart($browser, TestDefaults::SALES_CHANNEL);

        //assert shipping method in cart is changed but shipping costs in cart is not changed
        static::assertArrayHasKey('name', $cart['deliveries'][0]['shippingMethod']);
        static::assertEquals('Example shipping', $cart['deliveries'][0]['shippingMethod']['name']);

        static::assertArrayHasKey('unitPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertEquals(5, $cart['deliveries'][0]['shippingCosts']['unitPrice']);

        static::assertArrayHasKey('totalPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertEquals(5, $cart['deliveries'][0]['shippingCosts']['totalPrice']);

        $browser->request(
            'PATCH',
            $this->getRootProxyUrl('/modify-shipping-costs'),
            [],
            [],
            [],
            json_encode([
                'shippingCosts' => [
                    'unitPrice' => 0,
                    'totalPrice' => 0,
                ],
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
            ]) ?: ''
        );

        $response = $this->getBrowser()->getResponse();

        //assert response format
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNotEmpty($response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $cart = $this->getCart($browser, TestDefaults::SALES_CHANNEL);

        //assert shipping costs in cart
        static::assertArrayHasKey('unitPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertEquals(0, $cart['deliveries'][0]['shippingCosts']['unitPrice']);

        static::assertArrayHasKey('totalPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertEquals(0, $cart['deliveries'][0]['shippingCosts']['totalPrice']);
    }

    public function testModifyShippingCostsManuallyInCaseCartIsEmpty(): void
    {
        $salesChannelContext = $this->createDefaultSalesChannelContext();

        $salesChannelContext->setPermissions([ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES]);
        $payload = $this->contextPersister->load($salesChannelContext->getToken(), $salesChannelContext->getSalesChannel()->getId());
        $payload[SalesChannelContextService::PERMISSIONS][ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES] = true;
        $this->contextPersister->save($salesChannelContext->getToken(), $payload, $salesChannelContext->getSalesChannel()->getId());

        $browser = $this->createCart(TestDefaults::SALES_CHANNEL);

        $firstProductId = $this->ids->get('p1');
        $secondProductId = $this->ids->get('p2');
        $this->createTestFixtureProduct($firstProductId, 119, 19, $this->getContainer(), $salesChannelContext);
        $this->createTestFixtureProduct($secondProductId, 200, 10, $this->getContainer(), $salesChannelContext);

        $this->addSingleLineItem($browser, TestDefaults::SALES_CHANNEL, [
            'id' => $firstProductId,
            'label' => $firstProductId,
            'referencedId' => $firstProductId,
            'quantity' => 1,
            'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'priceDefinition' => [
                'price' => 100,
                'taxRules' => [[
                    'taxRate' => 19,
                    'percentage' => 100,
                ]],
                'type' => 'quantity',
            ],
        ], $salesChannelContext->getToken());

        $this->addSingleLineItem($browser, TestDefaults::SALES_CHANNEL, [
            'id' => $secondProductId,
            'label' => $secondProductId,
            'referencedId' => $firstProductId,
            'quantity' => 1,
            'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'priceDefinition' => [
                'price' => 100,
                'taxRules' => [[
                    'taxRate' => 10,
                    'percentage' => 100,
                ]],
                'type' => 'quantity',
            ],
        ], $salesChannelContext->getToken());

        $this->modifyShippingCostsManually($browser, 20, $salesChannelContext->getToken());

        $cart = $this->getStoreApiCart($browser, TestDefaults::SALES_CHANNEL, $salesChannelContext->getToken());

        $shippingCosts = $cart['deliveries'][0]['shippingCosts'];

        //shipping costs are now based on manual value, tax rate will be mixed
        static::assertArrayHasKey('unitPrice', $shippingCosts);
        static::assertEquals(20, $shippingCosts['unitPrice']);

        static::assertArrayHasKey('totalPrice', $shippingCosts);
        static::assertEquals(20, $shippingCosts['totalPrice']);

        static::assertCount(2, $shippingCosts['calculatedTaxes']);
        static::assertEquals(19, $shippingCosts['calculatedTaxes'][0]['taxRate']);
        static::assertEquals(10, $shippingCosts['calculatedTaxes'][1]['taxRate']);

        //using store-api through proxy to remove all items in cart
        $this->storeAPIRemoveLineItems($browser, [$firstProductId, $secondProductId], $salesChannelContext->getToken());

        $cart = $this->getStoreApiCart($browser, TestDefaults::SALES_CHANNEL, $salesChannelContext->getToken());
        static::assertEmpty($cart['deliveries']);

        //adding a new product item to cart.
        $this->addSingleLineItem($browser, TestDefaults::SALES_CHANNEL, [
            'id' => $firstProductId,
            'label' => $firstProductId,
            'referencedId' => $firstProductId,
            'quantity' => 1,
            'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'priceDefinition' => [
                'price' => 100,
                'taxRules' => [[
                    'taxRate' => 19,
                    'percentage' => 100,
                ]],
                'type' => 'quantity',
            ],
        ], $salesChannelContext->getToken());

        $cart = $this->getStoreApiCart($browser, TestDefaults::SALES_CHANNEL, $salesChannelContext->getToken());

        //after re-adding a line item, there is one tax rate and the manual shipping costs are restored.
        $shippingCosts = $cart['deliveries'][0]['shippingCosts'];

        static::assertArrayHasKey('unitPrice', $shippingCosts);
        static::assertEquals(20, $shippingCosts['unitPrice']);

        static::assertArrayHasKey('totalPrice', $shippingCosts);
        static::assertEquals(20, $shippingCosts['totalPrice']);

        static::assertCount(1, $shippingCosts['calculatedTaxes']);
        static::assertEquals(19, $shippingCosts['calculatedTaxes'][0]['taxRate']);
    }

    public function testModifyShippingCostsManuallyInCaseCartIsNotEmpty(): void
    {
        $salesChannelContext = $this->createDefaultSalesChannelContext();

        $salesChannelContext->setPermissions([ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES]);
        $payload = $this->contextPersister->load($salesChannelContext->getToken(), $salesChannelContext->getSalesChannel()->getId());
        $payload[SalesChannelContextService::PERMISSIONS][ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES] = true;
        $this->contextPersister->save($salesChannelContext->getToken(), $payload, $salesChannelContext->getSalesChannel()->getId());

        $browser = $this->createCart(TestDefaults::SALES_CHANNEL);

        $firstProductId = $this->ids->get('p1');
        $secondProductId = $this->ids->get('p2');
        $this->createTestFixtureProduct($firstProductId, 119, 19, $this->getContainer(), $salesChannelContext);
        $this->createTestFixtureProduct($secondProductId, 200, 10, $this->getContainer(), $salesChannelContext);

        $this->addSingleLineItem($browser, TestDefaults::SALES_CHANNEL, [
            'id' => $firstProductId,
            'label' => $firstProductId,
            'referencedId' => $firstProductId,
            'quantity' => 1,
            'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'priceDefinition' => [
                'price' => 100,
                'taxRules' => [[
                    'taxRate' => 19,
                    'percentage' => 100,
                ]],
                'type' => 'quantity',
            ],
        ], $salesChannelContext->getToken());

        $this->addSingleLineItem($browser, TestDefaults::SALES_CHANNEL, [
            'id' => $secondProductId,
            'label' => $secondProductId,
            'referencedId' => $firstProductId,
            'quantity' => 1,
            'type' => LineItem::CREDIT_LINE_ITEM_TYPE,
            'priceDefinition' => [
                'price' => -100,
                'type' => 'absolute',
            ],
        ], $salesChannelContext->getToken());

        $this->modifyShippingCostsManually($browser, 20, $salesChannelContext->getToken());

        $cart = $this->getStoreApiCart($browser, TestDefaults::SALES_CHANNEL, $salesChannelContext->getToken());

        $shippingCosts = $cart['deliveries'][0]['shippingCosts'];

        //shipping costs are now based on manual value, there is one tax rate
        static::assertArrayHasKey('unitPrice', $shippingCosts);
        static::assertEquals(20, $shippingCosts['unitPrice']);

        static::assertArrayHasKey('totalPrice', $shippingCosts);
        static::assertEquals(20, $shippingCosts['totalPrice']);

        static::assertCount(1, $shippingCosts['calculatedTaxes']);
        static::assertEquals(19, $shippingCosts['calculatedTaxes'][0]['taxRate']);

        //using store-api through proxy to remove Product item in cart, keep Credit item.
        $this->storeAPIRemoveLineItems($browser, [$firstProductId], $salesChannelContext->getToken());

        //adding a new product item to cart.
        $this->addSingleLineItem($browser, TestDefaults::SALES_CHANNEL, [
            'id' => $firstProductId,
            'label' => $firstProductId,
            'referencedId' => $firstProductId,
            'quantity' => 1,
            'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'priceDefinition' => [
                'price' => 100,
                'taxRules' => [[
                    'taxRate' => 19,
                    'percentage' => 100,
                ]],
                'type' => 'quantity',
            ],
        ], $salesChannelContext->getToken());

        $cart = $this->getStoreApiCart($browser, TestDefaults::SALES_CHANNEL, $salesChannelContext->getToken());

        //shipping costs is still based on manual value
        $shippingCosts = $cart['deliveries'][0]['shippingCosts'];
        static::assertArrayHasKey('unitPrice', $shippingCosts);
        static::assertEquals(20, $shippingCosts['unitPrice']);

        static::assertArrayHasKey('totalPrice', $shippingCosts);
        static::assertEquals(20, $shippingCosts['totalPrice']);

        static::assertCount(1, $shippingCosts['calculatedTaxes']);
        static::assertEquals(19, $shippingCosts['calculatedTaxes'][0]['taxRate']);
    }

    public function testSwitchDeliveryMethodAndPriceWillBeCalculated(): void
    {
        $salesChannelContext = $this->createDefaultSalesChannelContext();
        $productId = Uuid::randomHex();
        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $salesChannelContext);

        $browser = $this->createCart(TestDefaults::SALES_CHANNEL, $salesChannelContext->getToken());
        $this->addProduct($browser, TestDefaults::SALES_CHANNEL, $productId);
        $cart = $this->getCart($browser, TestDefaults::SALES_CHANNEL);

        //assert shipping cost in cart is default from sales channel
        static::assertArrayHasKey('totalPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertEquals(0, $cart['deliveries'][0]['shippingCosts']['totalPrice']);

        //create a new shipping method and request to change
        $shippingMethodId = $this->createShippingMethod();
        $browser->request('PATCH', $this->getUrl(TestDefaults::SALES_CHANNEL, '/context'), [
            'shippingMethodId' => $shippingMethodId,
        ]);

        //assert response format
        $response = $this->getBrowser()->getResponse();
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNotEmpty($response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $cart = $this->getCart($browser, TestDefaults::SALES_CHANNEL);

        //assert shipping method and cost are changed
        static::assertArrayHasKey('name', $cart['deliveries'][0]['shippingMethod']);
        static::assertEquals('Test shipping method', $cart['deliveries'][0]['shippingMethod']['name']);

        static::assertArrayHasKey('totalPrice', $cart['deliveries'][0]['shippingCosts']);
        static::assertEquals(30, $cart['deliveries'][0]['shippingCosts']['totalPrice']);
    }

    public function testCreditItemProcessorTakeCustomItemIntoAccount(): void
    {
        $salesChannelContext = $this->createDefaultSalesChannelContext();
        $productId = $this->ids->get('p1');
        $salesChannelContext->setPermissions([ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES]);

        $payload = $this->contextPersister->load($salesChannelContext->getToken(), $salesChannelContext->getSalesChannel()->getId());
        $payload[SalesChannelContextService::PERMISSIONS][ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES] = true;
        $this->contextPersister->save($salesChannelContext->getToken(), $payload, $salesChannelContext->getSalesChannel()->getId());

        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $salesChannelContext);

        $browser = $this->createCart(TestDefaults::SALES_CHANNEL);

        $taxForCustomItem = 20;
        $taxForProductItem = 10;

        $this->addSingleLineItem($browser, TestDefaults::SALES_CHANNEL, [
            'id' => $productId,
            'label' => $productId,
            'referencedId' => $productId,
            'quantity' => 1,
            'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'priceDefinition' => [
                'price' => 100,
                'taxRules' => [[
                    'taxRate' => $taxForProductItem,
                    'percentage' => 100,
                ]],
                'type' => 'quantity',
            ],
        ], $salesChannelContext->getToken());

        $this->addSingleLineItem($browser, TestDefaults::SALES_CHANNEL, [
            'id' => $this->ids->get('p2'),
            'label' => $this->ids->get('p2'),
            'referencedId' => $this->ids->get('p2'),
            'quantity' => 1,
            'type' => LineItem::CUSTOM_LINE_ITEM_TYPE,
            'priceDefinition' => [
                'price' => 100,
                'taxRules' => [[
                    'taxRate' => $taxForCustomItem,
                    'percentage' => 100,
                ]],
                'type' => 'quantity',
            ],
        ], $salesChannelContext->getToken());

        $this->addSingleLineItem($browser, TestDefaults::SALES_CHANNEL, [
            'id' => $this->ids->get('p3'),
            'label' => $this->ids->get('p3'),
            'referencedId' => $this->ids->get('p3'),
            'quantity' => 1,
            'type' => LineItem::CREDIT_LINE_ITEM_TYPE,
            'priceDefinition' => [
                'price' => -100,
                'type' => 'absolute',
            ],
        ], $salesChannelContext->getToken());

        $cart = $this->getStoreApiCart($browser, TestDefaults::SALES_CHANNEL, $salesChannelContext->getToken());

        //assert there are 3 items in cart
        static::assertArrayHasKey('lineItems', $cart);
        static::assertCount(3, $cart['lineItems']);

        $creditLineItems = array_filter($cart['lineItems'], fn ($lineItem) => $lineItem['type'] === LineItem::CREDIT_LINE_ITEM_TYPE);

        //assert there is credit item in cart
        static::assertNotEmpty($creditLineItems);
        $creditLineItem = array_values($creditLineItems)[0];

        //assert there is calculated taxes for product and custom items in cart
        static::assertCount(2, $calculatedTaxes = $creditLineItem['price']['calculatedTaxes']);
        $calculatedTaxForCustomItem = array_filter($calculatedTaxes, fn ($tax) => $tax['taxRate'] === $taxForCustomItem);

        static::assertNotEmpty($calculatedTaxForCustomItem);
        static::assertCount(1, $calculatedTaxForCustomItem);

        $calculatedTaxForProductItem = array_filter($calculatedTaxes, fn ($tax) => $tax['taxRate'] === $taxForProductItem);

        static::assertNotEmpty($calculatedTaxForProductItem);
        static::assertCount(1, $calculatedTaxForProductItem);
    }

    public function testDisableAutomaticPromotions(): void
    {
        $salesChannelContext = $this->createDefaultSalesChannelContext();
        $this->createTestFixtureFixedDiscountPromotion(Uuid::randomHex(), 40, PromotionDiscountEntity::SCOPE_CART, null, $this->getContainer(), $salesChannelContext);

        $browser = $this->createCart(TestDefaults::SALES_CHANNEL, $salesChannelContext->getToken());

        $productId = Uuid::randomHex();
        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $salesChannelContext);
        $this->addProduct($browser, TestDefaults::SALES_CHANNEL, $productId);

        //There are 2 line items in cart including 1 product and 1 automatic promotion
        $cart = $this->getCart($browser, TestDefaults::SALES_CHANNEL);
        static::assertCount(2, $cart['lineItems']);
        static::assertSame('product', $cart['lineItems'][0]['type']);
        static::assertSame('promotion', $cart['lineItems'][1]['type']);

        //Call to disable automatic promotions
        $browser->request(
            'PATCH',
            $this->getRootProxyUrl('/disable-automatic-promotions'),
            ['salesChannelId' => $salesChannelContext->getSalesChannel()->getId()]
        );
        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());

        //There is 1 line item in cart. It is product
        $cart = $this->getCart($browser, TestDefaults::SALES_CHANNEL);
        static::assertCount(1, $cart['lineItems']);
        static::assertNotSame('promotion', $cart['lineItems'][0]['type']);
    }

    public function testDisableAutomaticPromotionDoesNotAffectPromotionCodes(): void
    {
        $salesChannelContext = $this->createDefaultSalesChannelContext();
        $this->createTestFixtureFixedDiscountPromotion(Uuid::randomHex(), 40, PromotionDiscountEntity::SCOPE_CART, null, $this->getContainer(), $salesChannelContext);

        $browser = $this->createCart(TestDefaults::SALES_CHANNEL, $salesChannelContext->getToken());

        $productId = Uuid::randomHex();
        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $salesChannelContext);
        $this->addProduct($browser, TestDefaults::SALES_CHANNEL, $productId);

        // Add promotion code into cart
        $promotionCode = Random::getAlphanumericString(5);
        $this->createTestFixtureAbsolutePromotion(Uuid::randomHex(), $promotionCode, 100, $this->getContainer());
        $this->addPromotionCodeByAPI($browser, TestDefaults::SALES_CHANNEL, $promotionCode);

        // Check there are automatic promotion and promotion code in cart
        $cart = $this->getCart($browser, TestDefaults::SALES_CHANNEL);
        static::assertCount(3, $cart['lineItems']);
        static::assertSame('product', $cart['lineItems'][0]['type']);
        static::assertSame('promotion', $cart['lineItems'][1]['type']);
        static::assertSame('promotion', $cart['lineItems'][2]['type']);
        static::assertSame($promotionCode, $cart['lineItems'][2]['referencedId']);

        // Call to disable automatic promotion
        $browser->request(
            'PATCH',
            $this->getRootProxyUrl('/disable-automatic-promotions'),
            ['salesChannelId' => $salesChannelContext->getSalesChannel()->getId()]
        );

        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());

        // Check automatic promotion code is disabled and exist the promotion code in cart
        $cart = $this->getCart($browser, TestDefaults::SALES_CHANNEL);
        static::assertCount(2, $cart['lineItems']);
        static::assertSame($promotionCode, $cart['lineItems'][1]['referencedId']);
    }

    public function testEnableAutomaticPromotions(): void
    {
        $salesChannelContext = $this->createDefaultSalesChannelContext();
        $this->createTestFixtureFixedDiscountPromotion(Uuid::randomHex(), 40, PromotionDiscountEntity::SCOPE_CART, null, $this->getContainer(), $salesChannelContext);

        $browser = $this->createCart(TestDefaults::SALES_CHANNEL, $salesChannelContext->getToken());

        $productId = Uuid::randomHex();
        $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $salesChannelContext);
        $this->addProduct($browser, TestDefaults::SALES_CHANNEL, $productId);

        $cart = $this->getCart($browser, TestDefaults::SALES_CHANNEL);

        static::assertCount(2, $cart['lineItems']);
        static::assertSame('product', $cart['lineItems'][0]['type']);
        static::assertSame('promotion', $cart['lineItems'][1]['type']);

        // Call to disable automatic promotion
        $browser->request(
            'PATCH',
            $this->getRootProxyUrl('/disable-automatic-promotions'),
            ['salesChannelId' => $salesChannelContext->getSalesChannel()->getId()]
        );

        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());

        // Check automatic promotion is disabled
        $cart = $this->getCart($browser, TestDefaults::SALES_CHANNEL);
        static::assertCount(1, $cart['lineItems']);
        static::assertNotSame('promotion', $cart['lineItems'][0]['type']);

        // Call to enable automatic promotion
        $browser->request(
            'PATCH',
            $this->getRootProxyUrl('/enable-automatic-promotions'),
            ['salesChannelId' => $salesChannelContext->getSalesChannel()->getId()]
        );

        static::assertEquals(200, $this->getBrowser()->getResponse()->getStatusCode());

        // Check automatic promotion is enabled
        $cart = $this->getCart($browser, TestDefaults::SALES_CHANNEL);
        static::assertCount(2, $cart['lineItems']);
        static::assertSame('product', $cart['lineItems'][0]['type']);
        static::assertSame('promotion', $cart['lineItems'][1]['type']);
    }

    public function testProxyCreateOrderWithInvalidSalesChannelId(): void
    {
        $this->getBrowser()->request('POST', $this->getCreateOrderApiUrl(Uuid::randomHex()));

        $response = $this->getBrowser()->getResponse()->getContent();
        $response = json_decode($response ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertEquals('FRAMEWORK__INVALID_SALES_CHANNEL', $response['errors'][0]['code'] ?? null);
    }

    public function testProxyCreateOrderPrivileges(): void
    {
        try {
            $salesChannelContext = $this->createDefaultSalesChannelContext();
            $customerId = $this->createCustomer($salesChannelContext, 'info@example.com', 'shopware');
            $productId = $this->ids->get('p1');
            $salesChannelContext->setPermissions([ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES]);
            $payload = $this->contextPersister->load($salesChannelContext->getToken(), $salesChannelContext->getSalesChannel()->getId());
            $payload[SalesChannelContextService::PERMISSIONS][ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES] = true;
            $payload = array_merge($payload, [
                'customerId' => $customerId,
                'paymentMethodId' => $this->getAvailablePaymentMethod()->getId(),
            ]);
            $this->contextPersister->save($salesChannelContext->getToken(), $payload, $salesChannelContext->getSalesChannel()->getId());

            $this->createTestFixtureProduct($productId, 119, 19, $this->getContainer(), $salesChannelContext);

            $browser = $this->createCart(TestDefaults::SALES_CHANNEL, $salesChannelContext->getToken());

            $this->addSingleLineItem($browser, TestDefaults::SALES_CHANNEL, [
                'id' => $productId,
                'label' => $productId,
                'referencedId' => $productId,
                'quantity' => 1,
                'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                'priceDefinition' => [
                    'price' => 100,
                    'taxRules' => [[
                        'taxRate' => 10,
                        'percentage' => 100,
                    ]],
                    'type' => 'quantity',
                ],
            ], $salesChannelContext->getToken());

            $this->addSingleLineItem($browser, TestDefaults::SALES_CHANNEL, [
                'id' => $this->ids->get('p2'),
                'label' => $this->ids->get('p2'),
                'referencedId' => $this->ids->get('p2'),
                'quantity' => 1,
                'type' => LineItem::CREDIT_LINE_ITEM_TYPE,
                'priceDefinition' => [
                    'price' => -100,
                    'type' => 'absolute',
                ],
            ], $salesChannelContext->getToken());

            $cart = $this->getCart($browser, TestDefaults::SALES_CHANNEL);

            static::assertCount(2, $cart['lineItems']);
            static::assertSame('product', $cart['lineItems'][0]['type']);
            static::assertSame('credit', $cart['lineItems'][1]['type']);

            $orderPrivileges = [
                'api_proxy_switch-customer',
                'order:create',
                'order_customer:create',
                'order_address:create',
                'order_delivery:create',
                'order_line_item:create',
                'order_transaction:create',
                'order_delivery_position:create',
                'mail_template_type:update',
                'customer:update',
            ];
            foreach ([true, false] as $testOrderOnly) {
                TestUser::createNewTestUser(
                    $browser->getContainer()->get(Connection::class),
                    $testOrderOnly ? $orderPrivileges : ['api_proxy_switch-customer', CreditOrderLineItemListener::ACL_ORDER_CREATE_DISCOUNT_PRIVILEGE],
                )->authorizeBrowser($browser);
                $browser->request('POST', $this->getCreateOrderApiUrl($salesChannelContext->getSalesChannel()->getId()));

                $response = $browser->getResponse()->getContent();
                $response = json_decode($response ?: '', true, 512, \JSON_THROW_ON_ERROR);

                static::assertArrayHasKey('errors', $response, print_r($response, true));
                static::assertEquals('FRAMEWORK__MISSING_PRIVILEGE_ERROR', $response['errors'][0]['code'] ?? null);
                static::assertStringContainsString(
                    $testOrderOnly ? CreditOrderLineItemListener::ACL_ORDER_CREATE_DISCOUNT_PRIVILEGE : 'order_line_item:create',
                    $response['errors'][0]['detail'] ?? ''
                );
            }

            TestUser::createNewTestUser(
                $browser->getContainer()->get(Connection::class),
                array_merge($orderPrivileges, [CreditOrderLineItemListener::ACL_ORDER_CREATE_DISCOUNT_PRIVILEGE])
            )->authorizeBrowser($browser);
            $browser->request('POST', $this->getCreateOrderApiUrl($salesChannelContext->getSalesChannel()->getId()));

            $response = $browser->getResponse();

            static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        } finally {
            $this->resetBrowser();
        }
    }

    public function testProxyCreateOrderWithHeadersAreCopied(): void
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

    private function getLangHeaderName(): string
    {
        return 'HTTP_' . mb_strtoupper(str_replace('-', '_', PlatformRequest::HEADER_LANGUAGE_ID));
    }

    /**
     * @param array<string, mixed> $expectedTranslations
     * @param array<string, mixed> $data
     */
    private function assertTranslation(
        array $expectedTranslations,
        array $data,
        string $salesChannelId,
        ?string $langOverride = null
    ): void {
        $baseResource = '/api/category';

        $categoryData = $data;
        $categoryData['active'] = true;
        if (!isset($categoryData['id'])) {
            $categoryData['id'] = Uuid::randomHex();
        }

        $this->getBrowser()->request('POST', $baseResource, [], [], [], json_encode($categoryData, \JSON_THROW_ON_ERROR) ?: '');
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(204, $response->getStatusCode());

        $this->assertEntityExists($this->getBrowser(), 'category', $categoryData['id']);

        $headers = ['HTTP_ACCEPT' => 'application/json'];
        if ($langOverride) {
            $headers[$this->getLangHeaderName()] = $langOverride;
        }

        $this->getBrowser()->request('GET', $this->getUrl($salesChannelId, '/category/' . $categoryData['id']), [], [], $headers);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $responseData = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        foreach ($expectedTranslations as $key => $expectedTranslation) {
            if (!\is_array($expectedTranslations[$key])) {
                static::assertEquals($expectedTranslations[$key], $responseData[$key]);
            } else {
                foreach ($expectedTranslations[$key] as $key2 => $expectedTranslation2) {
                    static::assertEquals($expectedTranslation[$key2], $responseData[$key][$key2]);
                }
            }
        }
    }

    private function createLanguage(string $langId, string $salesChannelId, ?string $fallbackId = null): void
    {
        $baseUrl = '/api';

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
            $this->getBrowser()->request('POST', $baseUrl . '/language', [], [], [], json_encode($parentLanguageData, \JSON_THROW_ON_ERROR) ?: '');
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

        $this->getBrowser()->request('POST', $baseUrl . '/language', [], [], [], json_encode($languageData, \JSON_THROW_ON_ERROR) ?: '');
        static::assertEquals(204, $this->getBrowser()->getResponse()->getStatusCode());

        $this->getBrowser()->request('GET', $baseUrl . '/language/' . $langId);
    }

    private function getUrl(string $salesChannelId, string $url): string
    {
        return sprintf(
            '/api/_proxy/store-api/%s/%s',
            $salesChannelId,
            ltrim($url, '/')
        );
    }

    private function getStoreApiUrl(string $salesChannelId, string $url): string
    {
        return sprintf(
            '/api/_proxy/store-api/%s/%s',
            $salesChannelId,
            ltrim($url, '/')
        );
    }

    private function getCreateOrderApiUrl(string $salesChannelId): string
    {
        return sprintf(
            '/api/_proxy-order/%s',
            $salesChannelId
        );
    }

    private function getRootProxyUrl(string $url): string
    {
        return sprintf(
            '/api/_proxy/%s',
            ltrim($url, '/')
        );
    }

    /**
     * @param array<string, mixed> $salesChannel
     *
     * @return array<string, mixed>
     */
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
            'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
        ];

        $salesChannel = array_merge_recursive($defaults, $salesChannel);

        $this->salesChannelRepository->create([$salesChannel], Context::createDefaultContext());

        return $salesChannel;
    }

    private function createCart(string $saleChannelId, ?string $contextToken = null): KernelBrowser
    {
        if ($contextToken !== null) {
            $this->getBrowser()->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
        }
        $this->getBrowser()->request('POST', $this->getUrl($saleChannelId, 'checkout/cart'));

        $response = $this->getBrowser()->getResponse();

        static::assertEquals(200, $response->getStatusCode());

        $browser = clone $this->getBrowser();
        $browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?: '');

        return $browser;
    }

    private function addProduct(KernelBrowser $browser, string $salesChannelId, string $id, int $quantity = 1): void
    {
        $browser->request(
            'POST',
            $this->getUrl($salesChannelId, 'checkout/cart/line-item'),
            [],
            [],
            [],
            json_encode([
                'items' => [
                    [
                        'type' => 'product',
                        'referencedId' => $id,
                        'quantity' => $quantity,
                    ],
                ],
            ]) ?: ''
        );
    }

    /**
     * @param array<mixed> $payload
     */
    private function addSingleLineItem(KernelBrowser $browser, string $salesChannelId, array $payload = [], ?string $contextToken = null): void
    {
        $browser->request(
            'POST',
            $this->getStoreApiUrl($salesChannelId, 'checkout/cart/line-item'),
            [],
            [],
            [
                'HTTP_SW_CONTEXT_TOKEN' => $contextToken,
            ],
            json_encode(['items' => [$payload]]) ?: ''
        );
    }

    private function modifyShippingCostsManually(KernelBrowser $browser, float $price, ?string $contextToken = null): void
    {
        $browser->request(
            'PATCH',
            $this->getRootProxyUrl('/modify-shipping-costs'),
            [],
            [],
            [
                'HTTP_SW_CONTEXT_TOKEN' => $contextToken,
            ],
            json_encode([
                'shippingCosts' => [
                    'unitPrice' => $price,
                    'totalPrice' => $price,
                ],
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
            ]) ?: ''
        );
    }

    /**
     * @param array<mixed> $ids
     */
    private function storeAPIRemoveLineItems(KernelBrowser $browser, array $ids, ?string $contextToken = null): void
    {
        $browser->request(
            'DELETE',
            $this->getStoreApiUrl(TestDefaults::SALES_CHANNEL, '/checkout/cart/line-item'),
            [],
            [],
            [
                'HTTP_SW_CONTEXT_TOKEN' => $contextToken,
            ],
            json_encode([
                'ids' => $ids,
            ], \JSON_THROW_ON_ERROR) ?: ''
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
            $this->getUrl($salesChannelId, 'checkout/cart/line-item'),
            [],
            [],
            [],
            json_encode([
                'items' => [
                    [
                        'id' => $lineItemId,
                        'quantity' => $quantity,
                    ],
                ],
            ]) ?: ''
        );
    }

    /**
     * @return array<mixed>
     */
    private function getCart(KernelBrowser $browser, string $salesChannelId): array
    {
        $browser->request('GET', $this->getUrl($salesChannelId, 'checkout/cart'));

        $cart = json_decode($browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        return $cart;
    }

    /**
     * @return array<mixed>
     */
    private function getStoreApiCart(KernelBrowser $browser, string $salesChannelId, string $contextToken): array
    {
        $browser->request('GET', $this->getStoreApiUrl($salesChannelId, 'checkout/cart'), [], [], [
            'HTTP_SW_CONTEXT_TOKEN' => $contextToken,
        ]);

        $cart = json_decode($browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        return $cart['data'] ?? $cart;
    }

    private function addPromotionCodeByAPI(KernelBrowser $browser, string $salesChannelId, string $code): void
    {
        $browser->request(
            'POST',
            $this->getUrl($salesChannelId, 'checkout/cart/line-item'),
            [],
            [],
            [],
            json_encode([
                'items' => [
                    [
                        'type' => 'promotion',
                        'referencedId' => $code,
                    ],
                ],
            ]) ?: ''
        );
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
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'Invoice',
                    'description' => 'Default payment method',
                ],
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
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

        return $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
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
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'shippingMethodId' => $shippingMethodId,
        ]], $this->context);

        return $shippingMethodId;
    }

    /**
     * @return array<string, string|int>
     */
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
