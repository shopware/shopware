<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
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
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionNotFoundError;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cart\ProductOutOfStockError;
use Shopware\Core\Defaults;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\Seo\StorefrontSalesChannelTestHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Integration\PaymentHandler\TestPaymentHandler;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Checkout\Cart\Error\PaymentMethodChangedError;
use Shopware\Storefront\Checkout\Cart\Error\ShippingMethodChangedError;
use Shopware\Storefront\Controller\CheckoutController;
use Shopware\Storefront\Framework\AffiliateTracking\AffiliateTrackingListener;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedHook;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedHook;
use Shopware\Storefront\Page\Checkout\Finish\CheckoutFinishPageLoadedHook;
use Shopware\Storefront\Page\Checkout\Offcanvas\CheckoutInfoWidgetLoadedHook;
use Shopware\Storefront\Page\Checkout\Offcanvas\CheckoutOffcanvasWidgetLoadedHook;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @internal
 */
class CheckoutControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    private const UUID_LENGTH = 32;
    private const PRODUCT_PRICE = 15.99;
    private const CUSTOMER_NAME = 'Tester';
    private const TEST_AFFILIATE_CODE = 'testAffiliateCode';
    private const TEST_CAMPAIGN_CODE = 'testCampaignCode';
    private const SHIPPING_METHOD_BLOCKED_ERROR_CONTENT = 'The shipping method "%s" is blocked for your current shopping cart.';
    private const SHIPPING_METHOD_CHANGED_ERROR_CONTENT = '"%s" shipping is not available for your current cart, the shipping was changed to "%s".';
    private const PAYMENT_METHOD_BLOCKED_ERROR_CONTENT = 'The payment method "Cash on delivery" is blocked for your current shopping cart.';
    private const PAYMENT_METHOD_CHANGED_ERROR_CONTENT = '"%s" payment is not available for your current cart, the payment was changed to "%s".';
    private const PROMOTION_NOT_FOUND_ERROR_CONTENT = 'Promotion with code "tn-08" could not be found.';
    private const PRODUCT_STOCK_REACHED_ERROR_CONTENT = 'The product "Test product" is not available any more';

    private ?string $customerId = null;

    /**
     * @param string|float|int|bool|null $customerComment
     */
    #[DataProvider('customerComments')]
    #[Group('slow')]
    public function testOrderCustomerComment($customerComment, ?string $savedCustomerComment): void
    {
        $order = $this->performOrder($customerComment);
        static::assertSame($savedCustomerComment, $order->getCustomerComment());
    }

    /**
     * @return array<mixed>
     */
    public static function customerComments(): array
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
        $orderCustomerEntity = $order->getOrderCustomer();
        static::assertNotNull($orderCustomerEntity);
        static::assertSame(self::CUSTOMER_NAME, $orderCustomerEntity->getLastName());
    }

    public function testOrderWithInactivePaymentMethod(): void
    {
        $this->expectException(PaymentMethodNotAvailableException::class);

        $this->performOrder('', false);
    }

    public function testOrderWithFailedPaymentMethod(): void
    {
        $contextToken = Uuid::randomHex();

        $cart = $this->fillCart($contextToken);

        $requestDataBag = $this->createRequestDataBag('');
        $salesChannelContext = $this->createSalesChannelContext($contextToken, $cart->getTransactions()->first()?->getPaymentMethodId());
        $request = $this->createRequest();
        $request->request->set('fail', true);

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

        $order = $this->performOrder('', true, $request);

        static::assertSame(self::TEST_AFFILIATE_CODE, $order->getAffiliateCode());
        static::assertSame(self::TEST_CAMPAIGN_CODE, $order->getCampaignCode());
    }

    public function testAffiliateTracking(): void
    {
        $request = $this->createRequest();
        $request->getSession()->set(AffiliateTrackingListener::CAMPAIGN_CODE_KEY, self::TEST_CAMPAIGN_CODE);

        $order = $this->performOrder('', true, $request);

        static::assertSame(self::TEST_CAMPAIGN_CODE, $order->getCampaignCode());
        static::assertNull($order->getAffiliateCode());
    }

    public function testCampaignOrderTracking(): void
    {
        $request = $this->createRequest();
        $request->getSession()->set(AffiliateTrackingListener::AFFILIATE_CODE_KEY, self::TEST_AFFILIATE_CODE);

        $order = $this->performOrder('', true, $request);

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
        static::assertLessThan(400, $response->getStatusCode(), (string) $response->getContent());
    }

    /**
     * @param array<string> $errorKeys
     */
    #[DataProvider('errorDataProvider')]
    public function testOffCanvasWithErrorsFlash(ErrorCollection $errors, array $errorKeys, bool $testSwitchToDefault = false): void
    {
        $browser = $this->getBrowserWithLoggedInCustomer();
        $browser->followRedirects(true);

        // Clear flashback from login and/or register
        /** @var Session $session */
        $session = $this->getSession();
        $session->getFlashBag()->clear();

        $browserSalesChannelId = $browser->getServerParameter('test-sales-channel-id');

        $productId = Uuid::randomHex();
        $this->createProductOnDatabase($productId, 'test.123', $browserSalesChannelId);

        foreach ($errors as $error) {
            $this->prepareErrors(
                $error,
                $browser,
                $browserSalesChannelId,
                $productId,
                $testSwitchToDefault
            );
        }

        // Always add a product to the cart
        $browser->request(
            'POST',
            '/checkout/product/add-by-number',
            [
                'number' => 'test.123',
            ]
        );
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $browser->request(
            'GET',
            '/checkout/offcanvas'
        );
        $response = $browser->getResponse();
        $contentReturn = $response->getContent();
        static::assertNotFalse($contentReturn);

        $crawler = new Crawler();
        $crawler->addHtmlContent($contentReturn);
        $errorContent = $crawler->filterXPath('//div[@class="alert-content-container"]')->text();
        foreach ($errorKeys as $errorKey) {
            static::assertStringContainsString($errorKey, $errorContent);
        }
    }

    /**
     * @param array<string> $errorKeys
     */
    #[DataProvider('errorDataProvider')]
    public function testConfirmWithErrorsFlash(ErrorCollection $errors, array $errorKeys, bool $testSwitchToDefault = false, bool $orderShouldBeBlocked = false): void
    {
        $browser = $this->getBrowserWithLoggedInCustomer();
        $browser->followRedirects(true);
        $browserSalesChannelId = $browser->getServerParameter('test-sales-channel-id');

        $productId = Uuid::randomHex();
        $this->createProductOnDatabase($productId, 'test.123', $browserSalesChannelId);

        $stockError = false;

        foreach ($errors as $error) {
            if ($error instanceof ProductOutOfStockError) {
                // If not redirected e.g. 'ProductOutOfStockError' does
                $stockError = true;
            }

            $this->prepareErrors(
                $error,
                $browser,
                $browserSalesChannelId,
                $productId,
                $testSwitchToDefault
            );
        }

        // Always add a product to the cart
        $browser->request(
            'POST',
            '/checkout/product/add-by-number',
            [
                'number' => 'test.123',
            ]
        );
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $browser->request(
            'GET',
            '/checkout/confirm'
        );
        $response = $browser->getResponse();
        $contentReturn = $response->getContent();
        static::assertNotFalse($contentReturn);

        $crawler = new Crawler();
        $crawler->addHtmlContent($contentReturn);

        $errorContent = implode('', $crawler->filterXPath('//div[@class="alert-content-container"]')->each(static fn (Crawler $node) => $node->text()));
        foreach ($errorKeys as $errorKey) {
            static::assertStringContainsString($errorKey, $errorContent);
        }
        if ($testSwitchToDefault) {
            $activeShippingMethod = $crawler->filterXPath('//div[contains(concat(" ",normalize-space(@class)," "), " shipping-method-radio ")][input/@checked]')->text();
            static::assertStringContainsString('Standard', $activeShippingMethod);

            $activePaymentMethod = $crawler->filterXPath('//div[contains(concat(" ",normalize-space(@class)," "), " payment-method-radio ")][input/@checked]')->text();
            static::assertStringContainsString('Paid in advance', $activePaymentMethod);
        }

        // Ensure submit order button is disabled
        if (!$stockError) {
            $submitButton = $crawler->filterXPath('//button[@id="confirmFormSubmit"][@disabled]');
            static::assertCount(($orderShouldBeBlocked || $errors->blockOrder()) ? 1 : 0, $submitButton);
        }
    }

    /**
     * @return array<array<mixed>>
     */
    public static function errorDataProvider(): array
    {
        return [
            // One shipping method blocked is expected to be switched
            [
                new ErrorCollection(
                    [
                        new ShippingMethodChangedError('Standard', 'Express'),
                    ]
                ),
                [
                    \sprintf(self::SHIPPING_METHOD_CHANGED_ERROR_CONTENT, 'Standard', 'Express'),
                ],
            ],
            // All shipping methods blocked expected to stay blocked
            [
                new ErrorCollection(
                    [
                        new ShippingMethodChangedError('Standard', 'Express'),
                        new ShippingMethodChangedError('Express', 'Standard'),
                    ]
                ),
                [
                    \sprintf(self::SHIPPING_METHOD_BLOCKED_ERROR_CONTENT, 'Express'),
                ],
                false,
                true,
            ],
            // One payment method blocked is expected to be switched
            [
                new ErrorCollection(
                    [
                        new PaymentMethodChangedError('Cash On Delivery', 'Paid in advance'),
                    ]
                ),
                [
                    \sprintf(self::PAYMENT_METHOD_CHANGED_ERROR_CONTENT, 'Cash on delivery', 'Paid in advance'),
                ],
            ],
            // All payment methods blocked expected to stay blocked
            [
                new ErrorCollection(
                    [
                        new PaymentMethodChangedError('Paid in advance', 'Direct Debit'),
                        new PaymentMethodChangedError('Direct Debit', 'Invoice'),
                        new PaymentMethodChangedError('Invoice', 'Cash On Delivery'),
                        new PaymentMethodChangedError('Cash On Delivery', 'Paid in advance'),
                    ]
                ),
                [
                    self::PAYMENT_METHOD_BLOCKED_ERROR_CONTENT,
                ],
                false,
                true,
            ],
            // Standard shipping and payment method blocked expected to switch both
            [
                new ErrorCollection(
                    [
                        new ShippingMethodChangedError('Standard', 'Express'),
                        new PaymentMethodChangedError('Cash On Delivery', 'Paid in advance'),
                    ]
                ),
                [
                    \sprintf(self::SHIPPING_METHOD_CHANGED_ERROR_CONTENT, 'Standard', 'Express'),
                    \sprintf(self::PAYMENT_METHOD_CHANGED_ERROR_CONTENT, 'Cash on delivery', 'Paid in advance'),
                ],
            ],
            // None defaults blocked, should switch to defaults
            [
                new ErrorCollection(
                    [
                        new ShippingMethodChangedError('Express', 'Standard'),
                        new PaymentMethodChangedError('Invoice', 'Paid in advance'),
                    ]
                ),
                [
                    \sprintf(self::SHIPPING_METHOD_CHANGED_ERROR_CONTENT, 'Express', 'Standard'),
                    \sprintf(self::PAYMENT_METHOD_CHANGED_ERROR_CONTENT, 'Invoice', 'Paid in advance'),
                ],
                true,
            ],
            // Promotion not found
            [
                new ErrorCollection(
                    [
                        new PromotionNotFoundError('tn-08'),
                    ]
                ),
                [
                    self::PROMOTION_NOT_FOUND_ERROR_CONTENT,
                ],
            ],
            // Product out of stock
            [
                new ErrorCollection(
                    [
                        new ProductOutOfStockError('product id', 'Car'),
                    ]
                ),
                [
                    self::PRODUCT_STOCK_REACHED_ERROR_CONTENT,
                ],
            ],
        ];
    }

    public function testCheckoutCartPageLoadedHookScriptsAreExecuted(): void
    {
        $browser = $this->getBrowserWithLoggedInCustomer();

        $browser->request(
            'GET',
            '/checkout/cart'
        );

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(CheckoutCartPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testCheckoutConfirmPageLoadedHookScriptsAreExecuted(): void
    {
        $contextToken = Uuid::randomHex();

        $cart = $this->fillCart($contextToken);

        $salesChannelContext = $this->createSalesChannelContext($contextToken, $cart->getTransactions()->first()?->getPaymentMethodId());
        $request = $this->createRequest($salesChannelContext);

        $this->getContainer()->get(CheckoutController::class)->confirmPage($request, $salesChannelContext);

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(CheckoutConfirmPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testJsonCart(): void
    {
        $browser = $this->getBrowserWithLoggedInCustomer();
        $browserSalesChannelId = $browser->getServerParameter('test-sales-channel-id');

        $productId = Uuid::randomHex();
        $this->createProductOnDatabase($productId, 'test.123', $browserSalesChannelId);

        // Always add a product to the cart
        $browser->request(
            'POST',
            '/checkout/product/add-by-number',
            [
                'number' => 'test.123',
            ]
        );

        $browser->request('GET', '/checkout/cart.json');

        $response = $browser->getResponse();

        static::assertEquals(200, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);

        static::assertArrayHasKey('price', $content);
        static::assertArrayHasKey('lineItems', $content);
        static::assertArrayHasKey('deliveries', $content);
        static::assertArrayHasKey('errors', $content);
        static::assertArrayHasKey('transactions', $content);
        static::assertCount(1, $content['lineItems']);
        static::assertArrayHasKey('id', $content['lineItems'][0]);
        static::assertArrayHasKey('type', $content['lineItems'][0]);
        static::assertArrayHasKey('label', $content['lineItems'][0]);
        static::assertArrayHasKey('quantity', $content['lineItems'][0]);
    }

    public function testCheckoutFinishPageLoadedHookScriptsAreExecuted(): void
    {
        $contextToken = Uuid::randomHex();

        $order = $this->performOrder('', true, null, $contextToken);

        $salesChannelContext = $this->createSalesChannelContext($contextToken);
        $request = $this->createRequest($salesChannelContext);
        $request->request->set('orderId', $order->getId());
        $requestDataBag = $this->createRequestDataBag('');

        $this->getContainer()->get(CheckoutController::class)->finishPage($request, $salesChannelContext, $requestDataBag);

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(CheckoutFinishPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testCheckoutInfoWidget(): void
    {
        $contextToken = Uuid::randomHex();

        $cartService = $this->getContainer()->get(CartService::class);
        $cart = $cartService->createNew($contextToken);

        $productId = $this->createProduct();
        $salesChannelContext = $this->createSalesChannelContext($contextToken);

        $cart = $cartService->add(
            $cart,
            [new LineItem('lineItem1', LineItem::PRODUCT_LINE_ITEM_TYPE, $productId)],
            $salesChannelContext
        );

        $request = $this->createRequest($salesChannelContext);

        $response = $this->getContainer()->get(CheckoutController::class)->info($request, $salesChannelContext);
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        static::assertStringContainsString((string) $cart->getPrice()->getTotalPrice(), (string) $response->getContent());

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(CheckoutInfoWidgetLoadedHook::HOOK_NAME, $traces);
    }

    public function testCheckoutInfoWidgetSkipsCalculationAndRenderIfCartIsEmpty(): void
    {
        Feature::skipTestIfInActive('v6.5.0.0', $this);

        $contextToken = Uuid::randomHex();

        $cartService = $this->getContainer()->get(CartService::class);
        $cartService->createNew($contextToken);

        $salesChannelContext = $this->createSalesChannelContext($contextToken);
        $request = $this->createRequest($salesChannelContext);

        $response = $this->getContainer()->get(CheckoutController::class)->info($request, $salesChannelContext);
        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertEmpty($response->getContent());
    }

    public function testCheckoutOffcanvasWidgetLoadedHookScriptsAreExecuted(): void
    {
        $contextToken = Uuid::randomHex();

        $cart = $this->fillCart($contextToken);

        $salesChannelContext = $this->createSalesChannelContext($contextToken, $cart->getTransactions()->first()?->getPaymentMethodId());
        $request = $this->createRequest($salesChannelContext);

        $this->getContainer()->get(CheckoutController::class)->offcanvas($request, $salesChannelContext);

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(CheckoutOffcanvasWidgetLoadedHook::HOOK_NAME, $traces);
    }

    private function updateSalesChannel(string $salesChannelId): void
    {
        $snippetSetId = $this->getSnippetSetIdForLocale('en-GB');
        $paymentMethodId = $this->getValidPaymentMethodId();
        $shippingMethodId = $this->getValidShippingMethodId();
        $countryId = $this->getValidCountryId();

        $data = [
            'id' => $salesChannelId,
            'name' => 'test',
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => $snippetSetId,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $paymentMethodId,
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $shippingMethodId,
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $countryId,
            'countryVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'serviceCategoryVersionId' => Defaults::LIVE_VERSION,
            'footerCategoryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'paymentMethods' => [['id' => $paymentMethodId]],
            'shippingMethods' => [['id' => $shippingMethodId]],
            'countries' => [['id' => $countryId]],
            'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'domains' => [
                [
                    'id' => $salesChannelId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $snippetSetId,
                    'currencyId' => Defaults::CURRENCY,
                    'url' => 'http://test.123',
                ],
            ],
        ];
        $this->getContainer()->get('sales_channel.repository')->update([$data], Context::createDefaultContext());
    }

    private function createProductOnDatabase(string $productId, string $productNumber, string $salesChannelId): void
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
                    'salesChannelId' => $salesChannelId,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];
        $this->getContainer()->get('product.repository')->create([$product], $context);
    }

    /**
     * @param string|float|int|bool|null $customerComment
     */
    private function performOrder($customerComment, bool $paymentMethodActive = true, ?Request $request = null, ?string $contextToken = null): OrderEntity
    {
        if (!$contextToken) {
            $contextToken = Uuid::randomHex();
        }

        $cart = $this->fillCart($contextToken, $paymentMethodActive);

        $requestDataBag = $this->createRequestDataBag($customerComment);
        $salesChannelContext = $this->createSalesChannelContext($contextToken, $cart->getTransactions()->first()?->getPaymentMethodId());
        if (!$request instanceof Request) {
            $request = $this->createRequest();
        }

        /** @var RedirectResponse|Response $response */
        $response = $this->getContainer()->get(CheckoutController::class)->order($requestDataBag, $salesChannelContext, $request);

        static::assertInstanceOf(RedirectResponse::class, $response);

        $orderId = mb_substr($response->getTargetUrl(), -self::UUID_LENGTH);

        /** @var EntityRepository $orderRepo */
        $orderRepo = $this->getContainer()->get('order.repository');

        /** @var OrderEntity|null $order */
        $order = $orderRepo->search(new Criteria([$orderId]), Context::createDefaultContext())->first();

        static::assertNotNull($order);

        return $order;
    }

    private function createCustomer(): string
    {
        if ($this->customerId) {
            return $this->customerId;
        }

        $this->customerId = Uuid::randomHex();
        $salutationId = $this->getValidSalutationId();

        $customer = [
            'id' => $this->customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'id' => $this->customerId,
                'firstName' => 'Test',
                'lastName' => self::CUSTOMER_NAME,
                'city' => 'Schöppingen',
                'street' => 'Ebbinghoff 10',
                'zipcode' => '48624',
                'salutationId' => $salutationId,
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $this->customerId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'not12345',
            'firstName' => 'Test',
            'lastName' => self::CUSTOMER_NAME,
            'salutationId' => $salutationId,
            'customerNumber' => '12345',
        ];

        if (!Feature::isActive('v6.7.0.0')) {
            $customer['defaultPaymentMethodId'] = $this->getValidPaymentMethodId();
        }

        $this->getContainer()->get('customer.repository')->create([$customer], Context::createDefaultContext());

        return $this->customerId;
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

    private function fillCart(string $contextToken, bool $paymentMethodActive = true): Cart
    {
        $cart = $this->getContainer()->get(CartService::class)->createNew($contextToken);

        $productId = $this->createProduct();
        $cart->add(new LineItem('lineItem1', LineItem::PRODUCT_LINE_ITEM_TYPE, $productId));

        $cart->setTransactions($this->createTransaction($paymentMethodActive));

        return $cart;
    }

    private function createTransaction(bool $active = true): TransactionCollection
    {
        $paymentMethodId = Uuid::randomHex();

        $this->getContainer()->get('payment_method.repository')->upsert([[
            'id' => $paymentMethodId,
            'handlerIdentifier' => TestPaymentHandler::class,
            'name' => 'Test Payment',
            'technicalName' => 'payment_test',
            'description' => 'Test payment handler',
            'salesChannels' => [
                [
                    'id' => TestDefaults::SALES_CHANNEL,
                ],
            ],
            'active' => $active,
        ]], Context::createDefaultContext());

        return new TransactionCollection([
            new Transaction(
                new CalculatedPrice(
                    13.37,
                    13.37,
                    new CalculatedTaxCollection(),
                    new TaxRuleCollection()
                ),
                $paymentMethodId
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

    private function createSalesChannelContext(string $contextToken, ?string $paymentMethodId = null): SalesChannelContext
    {
        $this->updateSalesChannel(TestDefaults::SALES_CHANNEL);
        $salesChannelData = [
            SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(),
        ];
        if ($paymentMethodId !== null) {
            $salesChannelData[SalesChannelContextService::PAYMENT_METHOD_ID] = $paymentMethodId;
        }

        return $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            $contextToken,
            TestDefaults::SALES_CHANNEL,
            $salesChannelData
        );
    }

    private function createRequest(?SalesChannelContext $context = null): Request
    {
        $request = new Request();
        $request->setSession($this->getSession());

        $request->attributes->add([
            RequestTransformer::STOREFRONT_URL => EnvironmentHelper::getVariable('APP_URL'),
        ]);

        if ($context instanceof SalesChannelContext) {
            $request->attributes->add([
                PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $context,
            ]);
        }

        $request->request->set('noredirect', true);

        $requestStack = $this->getContainer()->get('request_stack');
        $requestStack->push($request);

        return $request;
    }

    /**
     * Prepares all conditions for the given error to occur on a cart page visit.
     */
    private function prepareErrors(
        Error $error,
        KernelBrowser $browser,
        string $salesChannelId,
        string $productId,
        bool $shouldSwitchToDefault
    ): void {
        $availabilityRuleId = $this->createAvailabilityRule($salesChannelId);
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        if ($error instanceof ShippingMethodChangedError) {
            $shippingMethodRepository = $this->getContainer()->get('shipping_method.repository');
            $blockedId = $this->getShippingMethodIdByName($error->getOldShippingMethodName());
            $newId = $this->getShippingMethodIdByName($error->getNewShippingMethodName());

            $shippingMethodRepository->update([
                [
                    'id' => $blockedId,
                    'availabilityRuleId' => $availabilityRuleId,
                ],
            ], Context::createDefaultContext());

            $salesChannelRepository->update([
                [
                    'id' => $salesChannelId,
                    'shippingMethodId' => $shouldSwitchToDefault ? $newId : $blockedId,
                ],
            ], Context::createDefaultContext());

            if ($shouldSwitchToDefault) {
                $browser->request(
                    'POST',
                    '/checkout/configure',
                    [
                        SalesChannelContextService::SHIPPING_METHOD_ID => $blockedId,
                    ]
                );
            }

            return;
        }

        if ($error instanceof PaymentMethodChangedError) {
            $paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
            $blockedId = $this->getPaymentMethodIdByName($error->getOldPaymentMethodName());
            $newId = $this->getPaymentMethodIdByName($error->getNewPaymentMethodName());

            $paymentMethodRepository->update([
                [
                    'id' => $blockedId,
                    'availabilityRuleId' => $availabilityRuleId,
                ],
            ], Context::createDefaultContext());

            $salesChannelRepository->update([
                [
                    'id' => $salesChannelId,
                    'paymentMethodId' => $shouldSwitchToDefault ? $newId : $blockedId,
                ],
            ], Context::createDefaultContext());

            if ($shouldSwitchToDefault) {
                $browser->request(
                    'POST',
                    '/checkout/configure',
                    [
                        SalesChannelContextService::PAYMENT_METHOD_ID => $blockedId,
                    ]
                );
            }

            return;
        }

        if ($error instanceof PromotionNotFoundError) {
            $browser->request(
                'POST',
                '/checkout/promotion/add',
                [
                    'code' => $error->getParameters()['code'],
                ]
            );

            return;
        }

        if ($error instanceof ProductOutOfStockError) {
            $productRepository = $this->getContainer()->get('product.repository');
            $productRepository->update([
                [
                    'id' => $productId,
                    'isCloseout' => true,
                    'stock' => 0,
                ],
            ], Context::createDefaultContext());

            return;
        }

        static::fail(\sprintf('Could not provoke error of type %s. Did you forget to implement it?', $error::class));
    }

    private function createAvailabilityRule(string $salesChannelId): string
    {
        $ruleRepository = $this->getContainer()->get('rule.repository');
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('name', 'NotAvailableWithTestSalesChannel')
        );
        $ruleId = $ruleRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
        if ($ruleId !== null) {
            return $ruleId;
        }

        $ruleId = Uuid::randomHex();
        $orContainerId = Uuid::randomHex();
        $andContainerId = Uuid::randomHex();
        $ruleRepository->create([
            [
                'id' => $ruleId,
                'name' => 'NotAvailableWithTestDefaultSalesChannel',
                'priority' => 1,
                'conditions' => [
                    [
                        'id' => $orContainerId,
                        'type' => 'orContainer',
                    ],
                    [
                        'id' => $andContainerId,
                        'type' => 'andContainer',
                        'parentId' => $orContainerId,
                    ],
                    [
                        'type' => 'salesChannel',
                        'parentId' => $andContainerId,
                        'value' => [
                            'operator' => '!=',
                            'salesChannelIds' => [
                                $salesChannelId,
                            ],
                        ],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        return $ruleId;
    }

    private function getShippingMethodIdByName(string $name): string
    {
        $shippingMethodRepository = $this->getContainer()->get('shipping_method.repository');
        $c = new Criteria();
        $c->addFilter(
            new EqualsFilter('name', $name)
        );

        $shippingMethodId = $shippingMethodRepository->searchIds($c, Context::createDefaultContext())->firstId();
        static::assertNotNull($shippingMethodId);

        return $shippingMethodId;
    }

    private function getPaymentMethodIdByName(string $name): string
    {
        $paymentMethodRepository = $this->getContainer()->get('payment_method.repository');
        $c = new Criteria();
        $c->addFilter(
            new EqualsFilter('name', $name)
        );

        $paymentMethodId = $paymentMethodRepository->searchIds($c, Context::createDefaultContext())->firstId();
        static::assertNotNull($paymentMethodId);

        return $paymentMethodId;
    }
}
