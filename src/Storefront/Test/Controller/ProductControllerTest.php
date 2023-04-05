<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Controller\ProductController;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Shopware\Storefront\Page\Product\QuickView\ProductQuickViewWidgetLoadedHook;
use Shopware\Storefront\Page\Product\Review\ReviewLoaderResult;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ProductControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

        $this->createSalesChannel([
            'id' => $this->ids->create('sales-channel'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://test.to',
                ],
            ],
        ]);
    }

    public function testForwardFromSaveReviewToLoadReviews(): void
    {
        $productId = $this->createProduct();

        $this->login();

        $response = $this->request(
            'POST',
            '/product/' . $productId . '/rating',
            $this->tokenize('frontend.detail.review.save', [
                'forwardTo' => 'frontend.product.reviews',
                'points' => 5,
                'title' => 'Test',
                'content' => 'Test content',
            ])
        );

        static::assertInstanceOf(StorefrontResponse::class, $response);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertInstanceOf(ReviewLoaderResult::class, $response->getData()['reviews']);
    }

    public function testSwitchOptionsToLoadOptionDefault(): void
    {
        $productId = $this->createProduct();

        $response = $this->request(
            'GET',
            '/detail/' . $productId . '/switch',
            $this->tokenize('frontend.detail.switch', [
                'productId' => $productId,
            ])
        );

        $responseContent = (string) $response->getContent();
        $content = (array) json_decode($responseContent);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertEquals($productId, $content['productId']);
        static::assertStringContainsString($productId, $content['url']);
    }

    public function testSwitchDoesNotCrashOnMalformedOptions(): void
    {
        $productId = $this->createProduct();

        $response = $this->request(
            'GET',
            '/detail/' . $productId . '/switch',
            $this->tokenize('frontend.detail.switch', [
                'productId' => $productId,
                'options' => 'notJson',
            ])
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    /**
     * @dataProvider variantProvider
     */
    public function testVariantGrayedOut(
        string $requestVariant,
        bool $blue,
        bool $green,
        bool $red,
        bool $l,
        bool $xl,
        bool $shouldThrowException = false
    ): void {
        $products = (new ProductBuilder($this->ids, 'a.0'))
            ->manufacturer('m1')
            ->name('test')
            ->price(10)
            ->visibility()
            ->configuratorSetting('red', 'color')
            ->configuratorSetting('green', 'color')
            ->configuratorSetting('blue', 'color')
            ->configuratorSetting('l', 'size')
            ->configuratorSetting('xl', 'size')
            ->configuratorSetting('m', 'size')
            ->stock(10)
            ->closeout()
            ->variant(
                (new ProductBuilder($this->ids, 'a.1'))
                    ->option('red', 'color')
                    ->option('xl', 'size')
                    ->stock(0)
                    ->closeout(false)
                    ->build()
            )
            ->variant(
                (new ProductBuilder($this->ids, 'a.2'))
                    ->option('green', 'color')
                    ->option('xl', 'size')
                    ->stock(0)
                    ->closeout(null) // inherited
                    ->build()
            )
            ->variant(
                (new ProductBuilder($this->ids, 'a.3'))
                    ->option('red', 'color')
                    ->option('l', 'size')
                    ->stock(10)
                    ->closeout(null) // inherited
                    ->build()
            )
            ->variant(
                (new ProductBuilder($this->ids, 'a.4'))
                    ->option('green', 'color')
                    ->option('l', 'size')
                    ->stock(10)
                    ->closeout(false)
                    ->build()
            )
            ->variant(
                (new ProductBuilder($this->ids, 'a.5'))
                    ->option('blue', 'color')
                    ->option('xl', 'size')
                    ->visibility()
                    ->visibility($this->ids->get('sales-channel'))
                    ->stock(10)
                    ->closeout(null) // inherited
                    ->build()
            )
            ->variant(
                (new ProductBuilder($this->ids, 'a.6'))
                    ->option('blue', 'color')
                    ->option('l', 'size')
                    ->visibility($this->ids->get('sales-channel'))
                    ->stock(10)
                    ->closeout(null) // inherited
                    ->build()
            )
            ->variant(
                (new ProductBuilder($this->ids, 'a.7'))
                    ->option('red', 'color')
                    ->option('m', 'size')
                    ->visibility($this->ids->get('sales-channel'))
                    ->stock(10)
                    ->closeout(null) // inherited
                    ->build()
            )
            ->variant(
                (new ProductBuilder($this->ids, 'a.8'))
                    ->option('green', 'color')
                    ->option('m', 'size')
                    ->visibility($this->ids->get('sales-channel'))
                    ->stock(0)
                    ->closeout(null) // inherited
                    ->build()
            )
            ->variant(
                (new ProductBuilder($this->ids, 'a.9'))
                    ->option('blue', 'color')
                    ->option('m', 'size')
                    ->visibility($this->ids->get('sales-channel'))
                    ->stock(0)
                    ->closeout(false)
                    ->build()
            )
            ->build();

        $this->getContainer()->get('product.repository')->create([$products], Context::createDefaultContext());

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $controller = $this->getContainer()->get(ProductController::class);

        if ($shouldThrowException) {
            static::expectException(ProductNotFoundException::class);
        }

        $response = $controller->index($context, $this->createDetailRequest($context, $this->ids->get($requestVariant)));

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $crawler = new Crawler();
        $crawler->addHtmlContent((string) $response->getContent());

        $blueFound = false;
        $greenFound = false;
        $redFound = false;
        $xlFound = false;
        $lFound = false;
        $mFound = false;

        $crawler->filter('.product-detail-configurator .product-detail-configurator-option-label')
            ->each(static function (Crawler $option) use ($blue, $green, $red, $xl, $l, &$blueFound, &$greenFound, &$redFound, &$xlFound, &$lFound, &$mFound): void {
                if ($option->text() === 'blue') {
                    static::assertEquals($blue, $option->matches('.is-combinable'));
                    $blueFound = true;
                }

                if ($option->text() === 'green') {
                    static::assertEquals($green, $option->matches('.is-combinable'));
                    $greenFound = true;
                }

                if ($option->text() === 'red') {
                    static::assertEquals($red, $option->matches('.is-combinable'));
                    $redFound = true;
                }

                if ($option->text() === 'xl') {
                    static::assertEquals($xl, $option->matches('.is-combinable'));
                    $xlFound = true;
                }

                if ($option->text() === 'l') {
                    static::assertEquals($l, $option->matches('.is-combinable'));
                    $lFound = true;
                }

                if ($option->text() === 'm') {
                    $mFound = true;
                }
            });

        static::assertTrue($blueFound, 'Option blue was not found.');
        static::assertTrue($greenFound, 'Option green was not found.');
        static::assertTrue($redFound, 'Option red was not found.');
        static::assertTrue($xlFound, 'Option xl was not found.');
        static::assertTrue($lFound, 'Option l was not found.');
        static::assertFalse($mFound, 'Option m was found.');
    }

    /**
     * @return iterable<string, array<int, string|bool>>
     */
    public static function variantProvider(): iterable
    {
        yield 'test color: red - size: xl' => ['a.1', true, false, true, true, true]; // a.1 all options should be normal
        yield 'test color: green - size: xl' => ['a.2', true, false, true, true, false]; // a.2 green and xl should be gray
        yield 'test color: red - size: l' => ['a.3', false, true, true, true, true]; // a.3 all options should be normal except blue
        yield 'test color: green - size: l' => ['a.4', false, true, true, true, false]; // a.4 xl and blue should be gray
        yield 'test color: blue - size: xl' => ['a.5', true, false, true, false, true]; // a.5 l, green should be gray
        yield 'test color: blue - size: l' => ['a.6', false, false, false, false, false, true]; // a.6 xl should throw exception
        yield 'test color: red - size: m' => ['a.7', false, false, false, false, false, true]; // a.7 m should throw exception
        yield 'test color: green - size: m' => ['a.8', false, false, false, false, false, true]; // a.8 m should throw exception
        yield 'test color: blue - size: m' => ['a.9', false, false, false, false, false, true]; // a.9 m should throw exception
    }

    public function testProductPageLoadedScriptsAreExecuted(): void
    {
        $productId = $this->createProduct();

        $response = $this->request(
            'GET',
            '/detail/' . $productId,
            []
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey('product-page-loaded', $traces);
    }

    public function testMProductQuickViewWidgetLoadedHookScriptsAreExecuted(): void
    {
        $productId = $this->createProduct();

        $response = $this->request(
            'GET',
            '/quickview/' . $productId,
            []
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(ProductQuickViewWidgetLoadedHook::HOOK_NAME, $traces);
    }

    public function testProductReviewsLoadedScriptsAreExecuted(): void
    {
        $productId = $this->createProduct();

        $response = $this->request(
            'GET',
            '/product/' . $productId . '/reviews',
            []
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = $this->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey('product-reviews-loaded', $traces);
    }

    private function createDetailRequest(SalesChannelContext $context, string $productId): Request
    {
        $request = new Request();
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, $_SERVER['APP_URL']);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, $context->getSalesChannelId());
        $request->attributes->set('productId', $productId);

        $this->getContainer()->get('request_stack')->push($request);

        return $request;
    }

    private function createProduct(): string
    {
        $id = Uuid::randomHex();

        $ids = $this->getContainer()->get(Connection::class)
            ->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM sales_channel');

        $product = [
            'id' => $id,
            'productNumber' => $id,
            'stock' => 5,
            'name' => 'Test',
            'isCloseout' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
            'visibilities' => array_map(static fn ($id) => ['salesChannelId' => $id, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL], $ids),
        ];

        $repository = $this->getContainer()->get('product.repository');

        $repository->create([$product], Context::createDefaultContext());

        return $id;
    }

    private function createCustomer(): CustomerEntity
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $data = [
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
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => 'testuser@example.com',
                'password' => 'test12345',
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ];

        $repo = $this->getContainer()->get('customer.repository');

        $repo->create($data, Context::createDefaultContext());

        return $repo->search(new Criteria([$customerId]), Context::createDefaultContext())->first();
    }

    private function login(): KernelBrowser
    {
        $customer = $this->createCustomer();

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $customer->getEmail(),
                'password' => 'test12345',
            ])
        );
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $browser->request('GET', '/');
        /** @var StorefrontResponse $response */
        $response = $browser->getResponse();
        static::assertNotNull($context = $response->getContext());
        static::assertNotNull($context->getCustomer());

        return $browser;
    }
}
