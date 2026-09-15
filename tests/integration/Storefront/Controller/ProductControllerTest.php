<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewsWidgetLoadedHook;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Controller\ProductController;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Page\Product\QuickView\ProductQuickViewWidgetLoadedHook;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
class ProductControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private const TEST_CONTENT = 'Test review content foo bar test';

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createSalesChannel([
            'id' => $this->ids->create('sales-channel'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'https://test.to',
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
                'content' => self::TEST_CONTENT,
            ])
        );

        $this->checkStatusCode($response);
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
        $content = json_decode($responseContent, true, 512, \JSON_THROW_ON_ERROR);

        $this->checkStatusCode($response);
        static::assertInstanceOf(JsonResponse::class, $response);
        static::assertSame($productId, $content['productId']);
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

        $this->checkStatusCode($response);
    }

    #[DataProvider('variantProvider')]
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

        static::getContainer()->get('product.repository')->create([$products], Context::createDefaultContext());

        $context = static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $controller = static::getContainer()->get(ProductController::class);

        if ($shouldThrowException) {
            if (!Feature::isActive('v6.8.0.0')) {
                $this->expectException(ProductNotFoundException::class);
            } else {
                $this->expectException(ProductException::class);
            }
        }

        $response = $controller->index($context, $this->createDetailRequest($context, $this->ids->get($requestVariant)));

        $this->checkStatusCode($response);

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
                if ($option->innerText() === 'blue') {
                    static::assertSame($blue, $option->matches('.is-combinable'));
                    $blueFound = true;
                }

                if ($option->innerText() === 'green') {
                    static::assertSame($green, $option->matches('.is-combinable'));
                    $greenFound = true;
                }

                if ($option->innerText() === 'red') {
                    static::assertSame($red, $option->matches('.is-combinable'));
                    $redFound = true;
                }

                if ($option->innerText() === 'xl') {
                    static::assertSame($xl, $option->matches('.is-combinable'));
                    $xlFound = true;
                }

                if ($option->innerText() === 'l') {
                    static::assertSame($l, $option->matches('.is-combinable'));
                    $lFound = true;
                }

                if ($option->innerText() === 'm') {
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
            '/my-product/' . $productId,
            []
        );

        $this->checkStatusCode($response);

        $traces = $this->getStorefrontRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey('product-page-loaded', $traces);
    }

    public function testProductManufacturerRelativeLinkIsNotNormalizedAsExternalUrl(): void
    {
        $productId = $this->createProduct(['manufacturer' => ['name' => 'linked-manufacturer', 'link' => '/manufacturer-test/']]);

        $response = $this->request('GET', '/my-product/' . $productId, []);

        $this->checkStatusCode($response);

        $crawler = new Crawler((string) $response->getContent());

        $manufacturerLink = $crawler->filter('a.product-detail-manufacturer-link');
        static::assertCount(1, $manufacturerLink);
        static::assertSame('/manufacturer-test/', $manufacturerLink->attr('href'));
    }

    public function testProductJsonLdContainsMerchantListingData(): void
    {
        Feature::skipTestIfInActive('JSON_LD_DATA', $this);

        $salesChannel = static::getContainer()->get('sales_channel.repository')
            ->search(new Criteria([$this->getSalesChannelId()]), Context::createDefaultContext())
            ->getEntities()
            ->first();
        static::assertInstanceOf(SalesChannelEntity::class, $salesChannel);

        $parentCategoryId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        static::getContainer()->get('category.repository')->create([
            [
                'id' => $parentCategoryId,
                'name' => 'Women',
                'parentId' => $salesChannel->getNavigationCategoryId(),
            ],
            [
                'id' => $categoryId,
                'name' => 'Dresses',
                'parentId' => $parentCategoryId,
            ],
        ], Context::createDefaultContext());

        $productId = $this->createProduct([
            'ean' => '00123456',
            'categories' => [['id' => $categoryId]],
            'mainCategories' => [[
                'id' => Uuid::randomHex(),
                'categoryId' => $categoryId,
                'salesChannelId' => $this->getSalesChannelId(),
            ]],
            'price' => [[
                'currencyId' => Defaults::CURRENCY,
                'gross' => 10,
                'net' => 9,
                'listPrice' => ['gross' => 15, 'net' => 13.5, 'linked' => false],
                'linked' => false,
            ]],
        ]);

        $response = $this->request('GET', '/my-product/' . $productId, []);
        $this->checkStatusCode($response);

        $crawler = new Crawler((string) $response->getContent());
        $productJsonLd = null;

        foreach ($crawler->filter('script[type="application/ld+json"]') as $script) {
            $data = \json_decode($script->textContent, true, 512, \JSON_THROW_ON_ERROR);
            if (($data['@type'] ?? null) === 'Product') {
                $productJsonLd = $data;
                break;
            }
        }

        static::assertIsArray($productJsonLd);
        static::assertSame('00123456', $productJsonLd['gtin8']);
        static::assertSame(['Women > Dresses'], $productJsonLd['category']);
        static::assertSame(15.0, $productJsonLd['offers']['priceSpecification']['price']);
        static::assertSame('https://schema.org/StrikethroughPrice', $productJsonLd['offers']['priceSpecification']['priceType']);
        static::assertSame('EUR', $productJsonLd['offers']['priceSpecification']['priceCurrency']);
    }

    #[DataProvider('jsonLdDeliveryTimeProvider')]
    public function testProductJsonLdConvertsDeliveryTimeToWholeDays(string $unit, int $min, int $max, int $expectedMin, int $expectedMax): void
    {
        Feature::skipTestIfInActive('JSON_LD_DATA', $this);

        $deliveryTimeId = Uuid::randomHex();
        static::getContainer()->get('delivery_time.repository')->create([[
            'id' => $deliveryTimeId,
            'name' => 'Test delivery time',
            'min' => $min,
            'max' => $max,
            'unit' => $unit,
        ]], Context::createDefaultContext());

        $productId = $this->createProduct(['deliveryTimeId' => $deliveryTimeId]);
        $response = $this->request('GET', '/my-product/' . $productId, []);
        $this->checkStatusCode($response);

        $productJsonLd = $this->extractProductJsonLd(new Crawler((string) $response->getContent()));
        $handlingTime = $productJsonLd['offers']['shippingDetails']['deliveryTime']['handlingTime'];

        static::assertSame($expectedMin, $handlingTime['minValue']);
        static::assertSame($expectedMax, $handlingTime['maxValue']);
        static::assertSame('DAY', $handlingTime['unitCode']);
    }

    /**
     * @return iterable<string, array{0: string, 1: int, 2: int, 3: int, 4: int}>
     */
    public static function jsonLdDeliveryTimeProvider(): iterable
    {
        yield 'days remain unchanged' => [DeliveryTimeEntity::DELIVERY_TIME_DAY, 1, 3, 1, 3];
        yield 'weeks are converted to days' => [DeliveryTimeEntity::DELIVERY_TIME_WEEK, 1, 3, 7, 21];
        yield 'months use a thirty day approximation' => [DeliveryTimeEntity::DELIVERY_TIME_MONTH, 1, 2, 30, 60];
        yield 'years use a three hundred sixty-five day approximation' => [DeliveryTimeEntity::DELIVERY_TIME_YEAR, 1, 1, 365, 365];
        yield 'hours are rounded up to whole days' => [DeliveryTimeEntity::DELIVERY_TIME_HOUR, 1, 2, 1, 1];
    }

    public function testProductJsonLdOmitsShippingDetailsWithoutDeliveryTime(): void
    {
        Feature::skipTestIfInActive('JSON_LD_DATA', $this);

        $productId = $this->createProduct();
        $response = $this->request('GET', '/my-product/' . $productId, []);
        $this->checkStatusCode($response);

        $productJsonLd = $this->extractProductJsonLd(new Crawler((string) $response->getContent()));

        static::assertArrayNotHasKey('shippingDetails', $productJsonLd['offers']);
    }

    public function testProductPageDepthMicrodataUsesDepthItemProp(): void
    {
        Feature::skipTestIfActive('JSON_LD_DATA', $this);

        $productId = $this->createProduct(['length' => 12.0]);

        $response = $this->request(
            'GET',
            '/my-product/' . $productId,
            []
        );

        $this->checkStatusCode($response);

        $content = $response->getContent();
        static::assertIsString($content);
        static::assertStringContainsString('<meta itemprop="depth"', $content);
        static::assertStringContainsString('content="12 mm"', $content);
        static::assertStringNotContainsString('itemprop="length"', $content);
    }

    public function testSeparateProductGalleryCmsElementRendersImageMicrodata(): void
    {
        Feature::skipTestIfActive('JSON_LD_DATA', $this);

        $cmsPageId = Uuid::randomHex();

        static::getContainer()->get('cms_page.repository')->create([
            [
                'id' => $cmsPageId,
                'type' => 'product_detail',
                'sections' => [
                    [
                        'id' => Uuid::randomHex(),
                        'type' => 'default',
                        'position' => 0,
                        'blocks' => [
                            [
                                'id' => Uuid::randomHex(),
                                'type' => 'image-gallery',
                                'position' => 0,
                                'sectionPosition' => 'main',
                                'slots' => [
                                    [
                                        'id' => Uuid::randomHex(),
                                        'type' => 'image-gallery',
                                        'slot' => 'imageGallery',
                                        'config' => [
                                            'sliderItems' => ['source' => 'mapped', 'value' => 'product.media'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $productId = $this->createProduct([
            'cmsPageId' => $cmsPageId,
            'media' => [
                [
                    'id' => Uuid::randomHex(),
                    'position' => 0,
                    'media' => [
                        'fileName' => 'gallery-image',
                    ],
                ],
            ],
        ]);

        $response = $this->request(
            'GET',
            '/my-product/' . $productId,
            []
        );

        $this->checkStatusCode($response);

        $content = $response->getContent();
        static::assertIsString($content);

        $crawler = new Crawler();
        $crawler->addHtmlContent($content);

        static::assertCount(1, $crawler->filter('img.gallery-slider-image[itemprop="image"]'));
    }

    public function testReferencePriceIsRenderedWithSingleCalculatedPrice(): void
    {
        $unitId = Uuid::randomHex();
        $ruleId = Uuid::randomHex();
        $productId = $this->createProduct([
            'unitId' => $unitId,
            'unit' => [
                'id' => $unitId,
                'shortCode' => 'ml',
                'name' => 'Milliliter',
            ],
            'purchaseUnit' => 500.0,
            'referenceUnit' => 1000.0,
            'prices' => [
                [
                    'quantityStart' => 1,
                    'rule' => [
                        'id' => $ruleId,
                        'priority' => 1,
                        'name' => 'Reference price rule',
                        'conditions' => [
                            [
                                'type' => 'orContainer',
                                'position' => 0,
                                'children' => [
                                    [
                                        'type' => 'andContainer',
                                        'position' => 0,
                                        'children' => [
                                            ['type' => 'alwaysValid', 'position' => 0],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 4.0, 'net' => 3.36, 'linked' => false],
                    ],
                ],
            ],
        ]);

        $response = $this->request(
            'GET',
            '/my-product/' . $productId,
            []
        );

        $this->checkStatusCode($response);

        $crawler = new Crawler();
        $crawler->addHtmlContent((string) $response->getContent());

        $referencePrice = $crawler->filter('.price-unit-reference-content');

        static::assertCount(1, $referencePrice);
        static::assertStringContainsString('/ 1000 Milliliter', $referencePrice->text());
    }

    public function testProductQuickViewWidgetLoadedHookScriptsAreExecuted(): void
    {
        $productId = $this->createProduct();

        $response = $this->request(
            'GET',
            '/quickview/' . $productId,
            []
        );

        $this->checkStatusCode($response);

        $traces = $this->getStorefrontRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(ProductQuickViewWidgetLoadedHook::HOOK_NAME, $traces);
    }

    public function testProductQuickViewManufacturerIsNotLinkedWithoutUrl(): void
    {
        $productId = $this->createProduct(['manufacturer' => ['name' => 'no-link-manufacturer']]);

        $response = $this->request('GET', '/quickview/' . $productId, []);

        $this->checkStatusCode($response);

        $crawler = new Crawler();
        $crawler->addHtmlContent((string) $response->getContent());

        $manufacturerLink = $crawler->filter('a.quickview-minimal-product-manufacturer');
        static::assertCount(0, $manufacturerLink);

        $manufacturer = $crawler->filter('span.quickview-minimal-product-manufacturer');
        static::assertCount(1, $manufacturer);
        static::assertStringContainsString('no-link-manufacturer', $manufacturer->text());
    }

    #[DataProvider('manufacturerLinkProvider')]
    public function testProductQuickViewManufacturerIsLinkedWithUrl(string $manufacturerUrl, string $expectedUrl): void
    {
        $productId = $this->createProduct(['manufacturer' => ['name' => 'linked-manufacturer', 'link' => $manufacturerUrl]]);

        $response = $this->request('GET', '/quickview/' . $productId, []);

        $this->checkStatusCode($response);

        $crawler = new Crawler();
        $crawler->addHtmlContent((string) $response->getContent());

        $manufacturerLink = $crawler->filter('a.quickview-minimal-product-manufacturer');
        static::assertCount(1, $manufacturerLink);
        static::assertSame($expectedUrl, $manufacturerLink->attr('href'));
        static::assertStringContainsString('linked-manufacturer', $manufacturerLink->text());

        static::assertCount(0, $crawler->filter('span.quickview-minimal-product-manufacturer'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function manufacturerLinkProvider(): iterable
    {
        yield 'host without scheme gets https scheme' => ['shopware.com', 'https://shopware.com'];
        yield 'absolute internal path stays relative' => ['/manufacturer-test/', '/manufacturer-test/'];
    }

    public function testProductReviewsLoadedScriptsAreExecuted(): void
    {
        $productId = $this->createProduct();

        $response = $this->request(
            'GET',
            '/product/' . $productId . '/reviews',
            []
        );

        $this->checkStatusCode($response);

        $traces = $this->getStorefrontRequestContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(ProductReviewsWidgetLoadedHook::HOOK_NAME, $traces);

        $content = $response->getContent();
        static::assertIsString($content);

        if (Feature::isActive('JSON_LD_DATA')) {
            static::assertStringContainsString('class="product-detail-review-item-content"', $content);
        } else {
            static::assertStringContainsString('class="product-detail-review-item-content"', $content);
            static::assertStringContainsString('itemprop="description"', $content);
        }

        static::assertStringContainsString(self::TEST_CONTENT, $content);
    }

    private function createDetailRequest(SalesChannelContext $context, string $productId): Request
    {
        $request = Request::create((string) EnvironmentHelper::getVariable('APP_URL'));
        $request->attributes->add([
            RequestTransformer::STOREFRONT_URL => $_SERVER['APP_URL'],
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $context,
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID => $context->getSalesChannelId(),
            'productId' => $productId,
            SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true,
        ]);

        static::getContainer()->get('request_stack')->push($request);

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractProductJsonLd(Crawler $crawler): array
    {
        foreach ($crawler->filter('script[type="application/ld+json"]') as $script) {
            $data = \json_decode($script->textContent, true, 512, \JSON_THROW_ON_ERROR);
            if (($data['@type'] ?? null) === 'Product') {
                static::assertIsArray($data);

                return $data;
            }
        }

        static::fail('Product JSON-LD was not found.');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createProduct(array $overrides = []): string
    {
        $id = Uuid::randomHex();

        $ids = static::getContainer()->get(Connection::class)
            ->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM sales_channel');

        $product = [
            'id' => $id,
            'productNumber' => $id,
            'stock' => 5,
            'name' => 'my-product',
            'isCloseout' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
            'visibilities' => array_map(static fn ($id) => ['salesChannelId' => $id, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL], $ids),
            'productReviews' => [
                [
                    'id' => Uuid::randomHex(),
                    'productId' => $id,
                    'customerId' => $this->createCustomer()->getId(),
                    'salesChannelId' => $this->getSalesChannelId(),
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'status' => true,
                    'title' => 'Test',
                    'content' => self::TEST_CONTENT,
                    'points' => 5,
                ],
            ],
        ];

        $product = array_replace_recursive($product, $overrides);

        $repository = static::getContainer()->get('product.repository');

        $repository->create([$product], Context::createDefaultContext());

        return $id;
    }

    private function createCustomer(): CustomerEntity
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
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
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $customerId . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => $customerId,
        ];

        $repo = static::getContainer()->get('customer.repository');

        $repo->create([$customer], Context::createDefaultContext());

        $entity = $repo->search(new Criteria([$customerId]), Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(CustomerEntity::class, $entity);

        return $entity;
    }

    private function login(): KernelBrowser
    {
        $customer = $this->createCustomer();

        $browser = KernelLifecycleManager::createBrowser(static::getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $customer->getEmail(),
                'password' => 'shopware',
            ])
        );
        $response = $browser->getResponse();
        $this->checkStatusCode($response);

        return $browser;
    }

    private function checkStatusCode(Response $response): void
    {
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r($response->getContent(), true));
    }
}
