<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Shopware\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopware\Core\Checkout\Order\Exception\PaymentMethodNotAvailableException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionNotFoundError;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Test\Payment\Handler\SyncTestFailedPaymentHandler;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cart\ProductOutOfStockError;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\Seo\StorefrontSalesChannelTestHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Controller\CheckoutController;
use Shopware\Storefront\Framework\AffiliateTracking\AffiliateTrackingListener;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckoutControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    private const UUID_LENGTH = 32;
    private const PRODUCT_PRICE = 15.99;
    private const CUSTOMER_NAME = 'Tester';
    private const TEST_AFFILIATE_CODE = 'testAffiliateCode';
    private const TEST_CAMPAIGN_CODE = 'testCampaignCode';
    private const SHIPPING_METHOD_BLOCKED_ERROR_CONTENT = 'The shipping method "Standard" is blocked for your current shopping cart.';
    private const PAYMENT_METHOD_BLOCKED_ERROR_CONTENT = 'The payment method "Cash On Delivery" is blocked for your current shopping cart.';
    private const PROMOTION_NOT_FOUND_ERROR_CONTENT = 'Promotion with code "tn-08" could not be found.';
    private const PRODUCT_STOCK_REACHED_ERROR_CONTENT = 'The product "Car" is not available any more';

    /**
     * @var string
     */
    private $failedPaymentMethodId;

    /**
     * @dataProvider customerComments
     * @group slow
     *
     * @param string|float|int|bool|null $customerComment
     */
    public function testOrderCustomerComment($customerComment, ?string $savedCustomerComment): void
    {
        $order = $this->performOrder($customerComment);
        static::assertSame($savedCustomerComment, $order->getCustomerComment());
    }

    public function customerComments(): array
    {
        return [
            ["  Hello, \nthis is a customer comment!  ", "Hello, \nthis is a customer comment!"],
            ['<script>alert("hello")</script>', 'alert("hello")'],
            ['<h1>Hello</h1><br><br>This is a Test! ', 'HelloThis is a Test!'],
            ['  ', null],
            ['', null],
            [1.2, '1.2'],
            [12, '12'],
            [true, '1'],
            [false, null],
            [null, null],
        ];
    }

    public function testOrder(): void
    {
        $order = $this->performOrder('');

        static::assertSame(self::PRODUCT_PRICE, $order->getPrice()->getTotalPrice());
        static::assertSame(self::CUSTOMER_NAME, $order->getOrderCustomer()->getLastName());
    }

    public function testOrderWithInactivePaymentMethod(): void
    {
        $this->expectException(PaymentMethodNotAvailableException::class);

        $this->performOrder('', true);
    }

    public function testOrderWithFailedPaymentMethod(): void
    {
        $this->createFailedPaymentMethodData();

        $contextToken = Uuid::randomHex();

        $this->fillCart($contextToken, false, true);

        $requestDataBag = $this->createRequestDataBag('');
        $salesChannelContext = $this->createSalesChannelContext($contextToken, true);
        $request = $this->createRequest();

        /** @var RedirectResponse|Response $response */
        $response = $this->getContainer()->get(CheckoutController::class)->order($requestDataBag, $salesChannelContext, $request);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertStringContainsString('/account/order/edit', $response->getTargetUrl(), 'Target Url does not point to /checkout/finish');
    }

    public function testAffiliateAndCampaignTracking(): void
    {
        $request = $this->createRequest();
        $request->getSession()->set(AffiliateTrackingListener::AFFILIATE_CODE_KEY, self::TEST_AFFILIATE_CODE);
        $request->getSession()->set(AffiliateTrackingListener::CAMPAIGN_CODE_KEY, self::TEST_CAMPAIGN_CODE);

        $order = $this->performOrder('', false, $request);

        static::assertSame(self::TEST_AFFILIATE_CODE, $order->getAffiliateCode());
        static::assertSame(self::TEST_CAMPAIGN_CODE, $order->getCampaignCode());
    }

    public function testAffiliateTracking(): void
    {
        $request = $this->createRequest();
        $request->getSession()->set(AffiliateTrackingListener::CAMPAIGN_CODE_KEY, self::TEST_CAMPAIGN_CODE);

        $order = $this->performOrder('', false, $request);

        static::assertSame(self::TEST_CAMPAIGN_CODE, $order->getCampaignCode());
        static::assertNull($order->getAffiliateCode());
    }

    public function testCampaignOrderTracking(): void
    {
        $request = $this->createRequest();
        $request->getSession()->set(AffiliateTrackingListener::AFFILIATE_CODE_KEY, self::TEST_AFFILIATE_CODE);

        $order = $this->performOrder('', false, $request);

        static::assertSame(self::TEST_AFFILIATE_CODE, $order->getAffiliateCode());
        static::assertNull($order->getCampaignCode());
    }

    public function testOrderWithEmptyCartDoesNotResultIn400StatusCode(): void
    {
        $browser = $this->getBrowserWithLoggedInCustomer();

        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/checkout/order',
            [
                'tos' => 'on',
            ]
        );

        $response = $browser->getResponse();
        static::assertLessThan(400, $response->getStatusCode(), $response->getContent());
    }

    /**
     * @dataProvider errorDataProvider
     */
    public function testOffCanvasWithErrorsFlash($errorTypes, $errorKeys): void
    {
        static::markTestSkipped('Flaky due to wrong flash message. Fix with NEXT-17888');

        $contextToken = Uuid::randomHex();
        $productId = Uuid::randomHex();
        $cartService = $this->getContainer()->get(CartService::class);
        $this->createProductOnDatabase($productId, 'test.123');

        $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            $contextToken,
            TestDefaults::SALES_CHANNEL
        );
        $this->updateSalesChannel(TestDefaults::SALES_CHANNEL);
        $request = $this->createRequest();
        $request->attributes->add([
            '_route' => 'frontend.cart.offcanvas',
            SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true,
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $salesChannelContext,
            RequestTransformer::STOREFRONT_URL => 'http://test.de',
            SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE => 'en-GB',
            SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID => $this->getSnippetSetIdForLocale('en-GB'),
        ]);
        $this->getContainer()->get('request_stack')->push($request);
        $cartLineItem = $cartService->getCart($contextToken, $salesChannelContext);
        foreach ($errorTypes as $errorType) {
            $cartLineItem->addErrors(
                $errorType
            );
        }
        $response = $this->getContainer()->get(CheckoutController::class)->offcanvas($request, $salesChannelContext);
        $contentReturn = $response->getContent();
        static::assertNotFalse($contentReturn);

        $crawler = new Crawler();
        $crawler->addHtmlContent($contentReturn);
        $errorContent = $crawler->filterXPath('//div[@class="alert-content"]')->text();
        foreach ($errorKeys as $errorKey) {
            static::assertStringContainsString($errorKey, $errorContent);
        }
    }

    public function errorDataProvider(): array
    {
        return [
            [[new ShippingMethodBlockedError('Standard'), new PaymentMethodBlockedError('Cash On Delivery')], [self::SHIPPING_METHOD_BLOCKED_ERROR_CONTENT, self::PAYMENT_METHOD_BLOCKED_ERROR_CONTENT]],
            [[new PaymentMethodBlockedError('Cash On Delivery')], [self::PAYMENT_METHOD_BLOCKED_ERROR_CONTENT]],
            [[new PromotionNotFoundError('tn-08')], [self::PROMOTION_NOT_FOUND_ERROR_CONTENT]],
            [[new ProductOutOfStockError('product id', 'Car')], [self::PRODUCT_STOCK_REACHED_ERROR_CONTENT]],
        ];
    }

    private function updateSalesChannel(string $salesChannelId): void
    {
        $data = [
            'id' => $salesChannelId,
            'name' => 'test',
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
            'countryId' => $this->getValidCountryId(),
            'countryVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'serviceCategoryVersionId' => Defaults::LIVE_VERSION,
            'footerCategoryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'currencyId' => Defaults::CURRENCY,
                    'url' => 'http://test.123',
                ],
            ],
        ];
        $this->getContainer()->get('sales_channel.repository')->update([$data], Context::createDefaultContext());
    }

    private function createProductOnDatabase(string $productId, string $productNumber): void
    {
        $taxId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $product = [
            'id' => $productId,
            'name' => 'Test product',
            'productNumber' => $productNumber,
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15.99, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['id' => $taxId, 'name' => 'testTaxRate', 'taxRate' => 15],
            'categories' => [
                ['id' => $productId, 'name' => 'Test category'],
            ],
            'visibilities' => [
                [
                    'id' => $productId,
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];
        $this->getContainer()->get('product.repository')->create([$product], $context);
    }

    /**
     * @param string|float|int|bool|null $customerComment
     */
    private function performOrder($customerComment, ?bool $useInactivePaymentMethod = false, ?Request $request = null): OrderEntity
    {
        $contextToken = Uuid::randomHex();

        $this->fillCart($contextToken, $useInactivePaymentMethod);

        $requestDataBag = $this->createRequestDataBag($customerComment);
        $salesChannelContext = $this->createSalesChannelContext($contextToken);
        if ($request === null) {
            $request = $this->createRequest();
        }

        /** @var RedirectResponse|Response $response */
        $response = $this->getContainer()->get(CheckoutController::class)->order($requestDataBag, $salesChannelContext, $request);

        static::assertInstanceOf(RedirectResponse::class, $response);

        $orderId = mb_substr($response->getTargetUrl(), -self::UUID_LENGTH);

        /** @var EntityRepositoryInterface $orderRepo */
        $orderRepo = $this->getContainer()->get('order.repository');

        /** @var OrderEntity|null $order */
        $order = $orderRepo->search(new Criteria([$orderId]), Context::createDefaultContext())->first();

        static::assertNotNull($order);

        return $order;
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $salutationId = $this->getValidSalutationId();
        $paymentMethodId = $this->getValidPaymentMethodId();

        $customer = [
            [
                'id' => $customerId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $customerId,
                    'firstName' => 'Test',
                    'lastName' => self::CUSTOMER_NAME,
                    'city' => 'Schöppingen',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'salutationId' => $salutationId,
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $customerId,
                'defaultPaymentMethodId' => $paymentMethodId,
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::randomHex() . '@example.com',
                'password' => 'not',
                'firstName' => 'Test',
                'lastName' => self::CUSTOMER_NAME,
                'salutationId' => $salutationId,
                'customerNumber' => '12345',
            ],
        ];

        $this->getContainer()->get('customer.repository')->create($customer, Context::createDefaultContext());

        return $customerId;
    }

    private function createProduct(): string
    {
        $productId = Uuid::randomHex();

        $product = [
            'id' => $productId,
            'name' => 'Test product',
            'productNumber' => '123456789',
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => self::PRODUCT_PRICE, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['id' => $productId, 'name' => 'shopware AG'],
            'tax' => ['id' => $productId, 'name' => 'testTaxRate', 'taxRate' => 15],
            'categories' => [
                ['id' => $productId, 'name' => 'Test category'],
            ],
            'visibilities' => [
                [
                    'id' => $productId,
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        $this->getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());

        return $productId;
    }

    private function fillCart(string $contextToken, ?bool $useInactivePaymentMethod = false, ?bool $useFailedPaymentMethod = false): void
    {
        $cart = $this->getContainer()->get(CartService::class)->createNew($contextToken);

        $productId = $this->createProduct();
        $cart->add(new LineItem('lineItem1', LineItem::PRODUCT_LINE_ITEM_TYPE, $productId));

        if ($useInactivePaymentMethod) {
            $cart->setTransactions($this->createTransactionWithInactivePaymentMethod());

            return;
        }
        if ($useFailedPaymentMethod) {
            $cart->setTransactions($this->createTransactionWithFailedPaymentMethod());

            return;
        }
        $cart->setTransactions($this->createTransaction());
    }

    private function createTransaction(): TransactionCollection
    {
        return new TransactionCollection([
            new Transaction(
                new CalculatedPrice(
                    13.37,
                    13.37,
                    new CalculatedTaxCollection(),
                    new TaxRuleCollection()
                ),
                $this->getValidPaymentMethodId()
            ),
        ]);
    }

    private function createTransactionWithInactivePaymentMethod(): TransactionCollection
    {
        return new TransactionCollection([
            new Transaction(
                new CalculatedPrice(
                    13.37,
                    13.37,
                    new CalculatedTaxCollection(),
                    new TaxRuleCollection()
                ),
                $this->getInactivePaymentMethodId()
            ),
        ]);
    }

    private function createTransactionWithFailedPaymentMethod(): TransactionCollection
    {
        return new TransactionCollection([
            new Transaction(
                new CalculatedPrice(
                    13.37,
                    13.37,
                    new CalculatedTaxCollection(),
                    new TaxRuleCollection()
                ),
                $this->failedPaymentMethodId
            ),
        ]);
    }

    /**
     * @param string|float|int|bool|null $customerComment
     */
    private function createRequestDataBag($customerComment): RequestDataBag
    {
        return new RequestDataBag(['tos' => true, OrderService::CUSTOMER_COMMENT_KEY => $customerComment]);
    }

    private function createSalesChannelContext(string $contextToken, ?bool $withFailedPaymentMethod = false): SalesChannelContext
    {
        $this->updateSalesChannel(TestDefaults::SALES_CHANNEL);
        $salesChannelData = [
            SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(),
        ];
        if ($withFailedPaymentMethod === true) {
            $salesChannelData[SalesChannelContextService::PAYMENT_METHOD_ID] = $this->failedPaymentMethodId;
        }

        return $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            $contextToken,
            TestDefaults::SALES_CHANNEL,
            $salesChannelData
        );
    }

    private function createRequest(): Request
    {
        $request = new Request();
        $request->setSession($this->getContainer()->get('session'));

        return $request;
    }

    private function createFailedPaymentMethodData(): string
    {
        $paymentId = Uuid::randomHex();
        $data = [
            [
                'id' => $paymentId,
                'name' => SyncTestFailedPaymentHandler::class,
                'active' => true,
                'handlerIdentifier' => SyncTestFailedPaymentHandler::class,
                'salesChannels' => [
                    [
                        'id' => TestDefaults::SALES_CHANNEL,
                    ],
                ],
            ],
        ];

        $this->getContainer()->get('payment_method.repository')
            ->create($data, Context::createDefaultContext());

        $this->failedPaymentMethodId = $paymentId;

        return $paymentId;
    }
}
