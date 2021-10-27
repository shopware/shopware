<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Controller\ProductController;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Framework\Routing\StorefrontResponse;
use Shopware\Storefront\Page\Product\Configurator\ProductCombinationFinder;
use Shopware\Storefront\Page\Product\Review\ReviewLoaderResult;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private TestDataCollection $ids;

    public function setUp(): void
    {
        $this->ids = new TestDataCollection();
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

        $productRepository = $this->createMock(ProductCombinationFinder::class);
        $productRepository->method('find')->willThrowException(
            new ProductNotFoundException($productId)
        );

        $response = $this->request(
            'GET',
            '/detail/' . $productId . '/switch',
            $this->tokenize('frontend.detail.switch', [
                'productId' => $productId,
            ])
        );

        $responseContent = $response->getContent();
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
        bool $green,
        bool $red,
        bool $l,
        bool $xl
    ): void {
        $products = (new ProductBuilder($this->ids, 'a.0'))
            ->manufacturer('m1')
            ->name('test')
            ->price(10)
            ->visibility(TestDefaults::SALES_CHANNEL, ProductVisibilityDefinition::VISIBILITY_ALL)
            ->configuratorSetting('red', 'color')
            ->configuratorSetting('green', 'color')
            ->configuratorSetting('l', 'size')
            ->configuratorSetting('xl', 'size')
            ->stock(10)
            ->closeout(true)
            ->variant(
                (new ProductBuilder($this->ids, 'a.1'))
                    ->option('red')
                    ->option('xl')
                    ->stock(0)
                    ->closeout(false)
                    ->build()
            )
            ->variant(
                (new ProductBuilder($this->ids, 'a.2'))
                    ->option('green')
                    ->option('xl')
                    ->stock(0)
                    ->closeout(null) // inherited
                    ->build()
            )
            ->variant(
                (new ProductBuilder($this->ids, 'a.3'))
                    ->option('red')
                    ->option('l')
                    ->stock(10)
                    ->closeout(null) // inherited
                    ->build()
            )
            ->variant(
                (new ProductBuilder($this->ids, 'a.4'))
                    ->option('green')
                    ->option('l')
                    ->stock(10)
                    ->closeout(false)
                    ->build()
            )
            ->build();

        $this->getContainer()->get('product.repository')->create([$products], $this->ids->context);

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $controller = $this->getContainer()->get(ProductController::class);

        $response = $controller->index($context, $this->createDetailRequest($context, $this->ids->get($requestVariant)));

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $crawler = new Crawler();
        $crawler->addHtmlContent($response->getContent());

        $greenFound = false;
        $redFound = false;
        $xlFound = false;
        $lFound = false;

        $crawler->filter('.product-detail-configurator .product-detail-configurator-option-label')
            ->each(static function (Crawler $option) use ($green, $red, $xl, $l, &$greenFound, &$redFound, &$xlFound, &$lFound): void {
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
            });

        static::assertTrue($greenFound, 'Option green was not found.');
        static::assertTrue($redFound, 'Option red was not found.');
        static::assertTrue($xlFound, 'Option xl was not found.');
        static::assertTrue($lFound, 'Option l was not found.');
    }

    public function variantProvider(): iterable
    {
        yield 'test color: red - size: xl' => ['a.1', false, true, true, true]; // a.1 all options should be normal
        yield 'test color: green - size: xl' => ['a.2', false, true, true, false]; // a.2 green and xl should be gray
        yield 'test color: red - size: l' => ['a.3', true, true, true, true]; // a.3 all options should be normal
        yield 'test color: green - size: l' => ['a.4', true, true, true, false]; // a.4 xl should be gray
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

    private function createProduct(array $config = []): string
    {
        $id = Uuid::randomHex();

        $product = [
            'id' => $id,
            'productNumber' => $id,
            'stock' => 5,
            'name' => 'Test',
            'isCloseout' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
            'visibilities' => [
                [
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        $product = array_replace_recursive($product, $config);

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
                'password' => 'test',
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
                'password' => 'test',
            ])
        );
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $browser->request('GET', '/');
        /** @var StorefrontResponse $response */
        $response = $browser->getResponse();
        static::assertNotNull($response->getContext()->getCustomer());

        return $browser;
    }
}
