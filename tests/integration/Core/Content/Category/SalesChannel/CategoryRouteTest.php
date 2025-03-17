<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Category\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Tests\Integration\Core\Content\Category\SalesChannel\fixtures\CategoryRouteInheritanceFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 *
 * @phpstan-type CmsInheritanceDataProviderActual array{
 *      activeLanguageCode: string,
 *      hasTemplate: list<string>,
 *      hasSlotOverride: list<string>,
 *  }
 * @phpstan-type CmsInheritanceDataProviderIterator iterable<array{
 *      actual: CmsInheritanceDataProviderActual,
 *      expected: string
 *  }>
 */
#[Group('store-api')]
#[Package('discovery')]
#[CoversClass(CategoryRoute::class)]
class CategoryRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const LANGUAGE_IDS = [
        'en' => Defaults::LANGUAGE_SYSTEM,
        'de' => '20354d7ae4fe47af8ff6187bc0dedede',
        'at' => '20354d7ae4fe47af8ff6187bc0aaaaaa',
    ];

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
    }

    public function testCmsPageResolved(): void
    {
        $this->createListingData();

        $this->browser->request(
            'GET',
            '/store-api/category/' . $this->ids->get('category')
        );

        $this->assertListingCmsPage($this->ids->get('category'), $this->ids->get('cms-page'));
    }

    public function testIncludesConsidered(): void
    {
        $this->createListingData();

        $this->browser->request(
            'POST',
            '/store-api/category/' . $this->ids->get('category'),
            [
                'includes' => [
                    'product_manufacturer' => ['id', 'name', 'options'],
                    'product' => ['id', 'name', 'manufacturer', 'tax'],
                    'product_listing' => ['aggregations', 'elements'],
                    'tax' => ['id', 'name'],
                ],
            ]
        );

        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $listing = $response['cmsPage']['sections'][0]['blocks'][0]['slots'][0]['data']['listing'];

        static::assertArrayNotHasKey('sortings', $listing);
        static::assertArrayNotHasKey('page', $listing);
        static::assertArrayNotHasKey('limit', $listing);

        static::assertArrayHasKey('manufacturer', $listing['aggregations']);
        $manufacturers = $listing['aggregations']['manufacturer'];

        foreach ($manufacturers['entities'] as $manufacturer) {
            static::assertSame(['name', 'id', 'apiAlias'], array_keys($manufacturer));
        }

        foreach ($listing['elements'] as $product) {
            static::assertSame(['name', 'tax', 'manufacturer', 'id', 'apiAlias'], array_keys($product));
            static::assertSame(['name', 'id', 'apiAlias'], array_keys($product['tax']));
        }
    }

    public function testHome(): void
    {
        $this->createListingData();

        $this->browser->request(
            'POST',
            '/store-api/category/home'
        );

        $this->assertListingCmsPage($this->ids->get('home-category'), $this->ids->get('home-cms-page'));
    }

    public function testCategoryOfTypeFolder(): void
    {
        $this->createListingData();

        $id = $this->ids->get('folder');
        $this->browser->request(
            'POST',
            '/store-api/category/' . $id
        );

        $this->assertError($id);
    }

    public function testCategoryOfTypeLink(): void
    {
        $this->createListingData();

        $id = $this->ids->get('link');
        $this->browser->request(
            'POST',
            '/store-api/category/' . $id
        );

        $this->assertError($id);
    }

    public function testHomeWithSalesChannelOverride(): void
    {
        $this->createListingData();

        /** @var EntityRepository $salesChannelRepository */
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $salesChannelRepository->upsert([[
            'id' => $this->ids->get('sales-channel'),
            'homeCmsPageId' => $this->ids->get('cms-page'),
        ]], Context::createDefaultContext());

        $this->browser->request(
            'POST',
            '/store-api/category/home'
        );

        $this->assertListingCmsPage($this->ids->get('home-category'), $this->ids->get('cms-page'));
    }

    /**
     * @return CmsInheritanceDataProviderIterator
     */
    public static function categoryCmsPageInheritanceDataProvider(): iterable
    {
        yield from CategoryRouteInheritanceFixtures::noOverridesDataProvider();
        yield from CategoryRouteInheritanceFixtures::mixedOverridesDataProvider();
        yield from CategoryRouteInheritanceFixtures::allOverridesDataProvider();

        yield from CategoryRouteInheritanceFixtures::enOverridesDataProvider();
        yield from CategoryRouteInheritanceFixtures::deOverridesDataProvider();
        yield from CategoryRouteInheritanceFixtures::atOverridesDataProvider();
    }

    /**
     *  Testing CMS slot inheritance with category overrides.
     *  - EN is System Default language
     *  - DE is without parent
     *  - AT inherits from DE
     *  Expected hierarchy: Overrides of categories (AT > DE > EN) > Templates of categories (AT > DE > EN)
     *
     * @param CmsInheritanceDataProviderActual $actual
     */
    #[DataProvider('categoryCmsPageInheritanceDataProvider')]
    public function testCategoryCmsPageInheritance(array $actual, string $expected): void
    {
        $context = Context::createDefaultContext();
        $this->createLanguages($context);
        $this->createTranslatedData($actual, $context);
        $this->createDomains($context);

        $this->browser->request(
            'POST',
            '/store-api/category/' . $this->ids->get('category'),
        );

        $this->assertLandingPageCmsPage(
            $this->ids->get('category'),
            $this->ids->get('cms-page'),
            $expected,
        );
    }

    /**
     * @return CmsInheritanceDataProviderIterator
     */
    public static function categoryCmsPageInheritanceWithDuplicatesInLanguageChainDataProvider(): iterable
    {
        yield from CategoryRouteInheritanceFixtures::duplicatesInLanguageChainDataProviderNoOverrides();
        yield from CategoryRouteInheritanceFixtures::duplicatesInLanguageChainDataProviderEnOverrides();
        yield from CategoryRouteInheritanceFixtures::duplicatesInLanguageChainDataProviderDeOverrides();
        yield from CategoryRouteInheritanceFixtures::duplicatesInLanguageChainDataProviderAllOverrides();
    }

    /**
     *  Testing CMS slot inheritance with category overrides, with multiple EN occurrences in language chain.
     *  - EN is System Default language
     *  - DE inherits from EN
     *  Expected hierarchy: Overrides of categories (DE > EN) > Templates of categories (DE > EN)
     *  Actual language chain hierarchy: DE > EN > EN
     *
     * @param CmsInheritanceDataProviderActual $actual
     */
    #[DataProvider('categoryCmsPageInheritanceWithDuplicatesInLanguageChainDataProvider')]
    public function testCategoryCmsPageInheritanceWithDuplicatesInLanguageChain(array $actual, string $expected): void
    {
        $context = Context::createDefaultContext();
        $this->createLanguageWithSystemDefaultParent($context);
        $this->createTranslatedData($actual, $context, [self::LANGUAGE_IDS['en'], self::LANGUAGE_IDS['de']]);
        $this->createDomainsWithSystemDefaultParent($context);

        $this->browser->request(
            'POST',
            '/store-api/category/' . $this->ids->get('category'),
        );

        $this->assertLandingPageCmsPage(
            $this->ids->get('category'),
            $this->ids->get('cms-page'),
            $expected,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function createProducts(): array
    {
        $product = [
            'name' => 'test',
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'active' => true,
        ];

        $products = [];
        for ($i = 0; $i < 5; ++$i) {
            $products[] = array_merge([
                'id' => $this->ids->create('product' . $i),
                'manufacturer' => ['id' => $this->ids->create('manufacturer-' . $i), 'name' => 'test-' . $i],
                'productNumber' => $this->ids->get('product' . $i),
            ], $product);
        }

        return $products;
    }

    private function createLanguages(Context $context): void
    {
        $languages = [[
            'id' => self::LANGUAGE_IDS['de'],
            'name' => 'TestGerman',
            'locale' => [
                'id' => $this->ids->create('locale-de'),
                'name' => 'TestGerman',
                'territory' => 'TestGermany',
                'code' => 'de-DE-test',
            ],
            'translationCodeId' => $this->ids->get('locale-de'),
        ], [
            'id' => self::LANGUAGE_IDS['at'],
            'name' => 'TestAustrian',
            'parentId' => self::LANGUAGE_IDS['de'],
            'locale' => [
                'id' => $this->ids->create('locale-at'),
                'name' => 'TestAustrian',
                'territory' => 'TestAustria',
                'code' => 'de-AT-test',
            ],
            'translationCodeId' => $this->ids->get('locale-at'),
        ]];

        $this->getContainer()->get('language.repository')->create($languages, $context);
    }

    private function createLanguageWithSystemDefaultParent(Context $context): void
    {
        $languages = [[
            'id' => self::LANGUAGE_IDS['de'],
            'name' => 'TestGerman',
            'parentId' => self::LANGUAGE_IDS['en'],
            'locale' => [
                'id' => $this->ids->create('locale-de'),
                'name' => 'TestGerman',
                'territory' => 'TestGermany',
                'code' => 'de-DE-test',
            ],
            'translationCodeId' => $this->ids->get('locale-de'),
        ]];

        $this->getContainer()->get('language.repository')->create($languages, $context);
    }

    private function createDomains(Context $context): void
    {
        $basicDomainData = [
            'salesChannelId' => $this->ids->get('sales-channel'),
            'currencyId' => Defaults::CURRENCY,
            'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
        ];

        $url = 'http://localhost:8000';
        $this->getContainer()->get('sales_channel_domain.repository')->create([[
            ...$basicDomainData,
            'id' => $this->ids->create('domain-en'),
            'languageId' => self::LANGUAGE_IDS['de'],
            'url' => $url . '/de',
        ], [
            ...$basicDomainData,
            'id' => $this->ids->create('domain-at'),
            'languageId' => self::LANGUAGE_IDS['at'],
            'url' => $url . '/at',
        ]], $context);
    }

    private function createDomainsWithSystemDefaultParent(Context $context): void
    {
        $basicDomainData = [
            'salesChannelId' => $this->ids->get('sales-channel'),
            'currencyId' => Defaults::CURRENCY,
            'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
        ];

        $url = 'http://localhost:8000';
        $this->getContainer()->get('sales_channel_domain.repository')->create([[
            ...$basicDomainData,
            'id' => $this->ids->create('domain-en'),
            'languageId' => self::LANGUAGE_IDS['de'],
            'url' => $url . '/de',
        ]], $context);
    }

    private function assertError(string $categoryId): void
    {
        static::assertIsString($this->browser->getResponse()->getContent());
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $error = new CategoryNotFoundException($categoryId);
        $expectedError = [
            'status' => (string) $error->getStatusCode(),
            'message' => $error->getMessage(),
        ];

        static::assertSame($expectedError['status'], $response['errors'][0]['status']);
        static::assertSame($expectedError['message'], $response['errors'][0]['detail']);
    }

    private function assertListingCmsPage(string $categoryId, string $cmsPageId): void
    {
        static::assertIsString($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame($categoryId, $response['id'], 'CategoryId does not match');
        static::assertIsArray($response['cmsPage']);
        static::assertSame('product_list', $response['cmsPage']['type']);

        static::assertSame($cmsPageId, $response['cmsPage']['id'], 'CmsPage.id does not match');
        static::assertSame($cmsPageId, $response['cmsPageId'], 'CmsPageId does not match');
        static::assertCount(1, $response['cmsPage']['sections']);

        static::assertCount(1, $response['cmsPage']['sections'][0]['blocks']);

        $block = $response['cmsPage']['sections'][0]['blocks'][0];

        static::assertSame('product-listing', $block['type']);
        static::assertCount(1, $block['slots']);

        $slot = $block['slots'][0];
        static::assertSame('product-listing', $slot['type']);
        static::assertArrayHasKey('listing', $slot['data']);

        $listing = $slot['data']['listing'];
        static::assertArrayHasKey('aggregations', $listing);
        static::assertArrayHasKey('elements', $listing);
    }

    private function assertLandingPageCmsPage(string $categoryId, string $cmsPageId, string $expected): void
    {
        static::assertIsString($this->browser->getResponse()->getContent());
        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame($categoryId, $response['id'], 'CategoryId does not match');
        static::assertIsArray($response['cmsPage']);
        static::assertSame('landingpage', $response['cmsPage']['type']);

        static::assertSame($cmsPageId, $response['cmsPage']['id'], 'CmsPage.id does not match');
        static::assertSame($cmsPageId, $response['cmsPageId'], 'CmsPageId does not match');
        static::assertCount(1, $response['cmsPage']['sections']);

        static::assertCount(1, $response['cmsPage']['sections'][0]['blocks']);

        $block = $response['cmsPage']['sections'][0]['blocks'][0];

        static::assertSame('text-teaser', $block['type']);
        static::assertCount(1, $block['slots']);

        $slot = $block['slots'][0];
        static::assertSame('text', $slot['type']);

        $config = $slot['config']['content'];
        static::assertSame('static', $config['source']);
        static::assertSame($expected, $config['value']);
    }

    /**
     * @param CmsInheritanceDataProviderActual $actualData
     * @param array<string> $languageIds
     */
    private function createTranslatedData(array $actualData, Context $context, array $languageIds = self::LANGUAGE_IDS): void
    {
        $slotId = $this->ids->create('slot');
        $templates = array_reduce($actualData['hasTemplate'], static function (array $accumulator, string $languageCode) {
            $accumulator[$languageCode] = [
                'languageId' => self::LANGUAGE_IDS[$languageCode],
                'config' => [
                    'content' => [
                        'source' => 'static',
                        'value' => $languageCode . ' Template',
                    ],
                ],
            ];

            return $accumulator;
        }, []);

        $overrides = array_reduce($actualData['hasSlotOverride'], static function (array $accumulator, string $languageCode) use ($slotId) {
            $accumulator[$languageCode] = [
                'languageId' => self::LANGUAGE_IDS[$languageCode],
                'slotConfig' => [
                    $slotId => [
                        'content' => [
                            'source' => 'static',
                            'value' => $languageCode . ' Override',
                        ],
                    ],
                ],
            ];

            return $accumulator;
        }, []);

        $category = [
            'id' => $this->ids->create('home-category'),
            'name' => 'Test',
            'type' => 'folder',
            'cmsPage' => [
                'id' => $this->ids->create('home-cms-page'),
                'type' => 'landingpage',
                'sections' => [[
                    'position' => 0,
                    'type' => 'default',
                    'blocks' => [[
                        'type' => 'text-teaser',
                        'position' => 1,
                        'slots' => [[
                            'id' => $slotId,
                            'type' => 'text',
                            'slot' => 'content',
                            'config' => [ // System Default will always be provided
                                'content' => [
                                    'source' => 'static',
                                    'value' => 'en Template',
                                ],
                            ],
                            'translations' => \array_values($templates) ?: null,
                        ]],
                    ]],
                ]],
            ],
            'slotConfig' => $overrides['en']['slotConfig'] ?? null,
            'translations' => \array_values($overrides) ?: null,
        ];

        $childCategory = [
            ...$category,
            'id' => $this->ids->create('category'),
            'parentId' => $category['id'],
            'type' => 'page',
            'cmsPage' => [
                ...$category['cmsPage'],
                'id' => $this->ids->create('cms-page'),
            ],
        ];

        $this->getContainer()->get('category.repository')->create([$category, $childCategory], $context);

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'navigationCategoryId' => $this->ids->get('home-category'),
            'languageId' => self::LANGUAGE_IDS[$actualData['activeLanguageCode']],
            'languages' => \array_map(static fn ($id) => ['id' => $id], $languageIds),
        ]);
    }

    private function createListingData(): void
    {
        $products = $this->createProducts();
        $homeCategory = [
            'id' => $this->ids->create('home-category'),
            'name' => 'Test',
            'type' => 'folder',
            'cmsPage' => [
                'id' => $this->ids->create('home-cms-page'),
                'type' => 'product_list',
                'sections' => [
                    [
                        'position' => 0,
                        'type' => 'sidebar',
                        'blocks' => [
                            [
                                'type' => 'product-listing',
                                'position' => 1,
                                'slots' => [
                                    ['type' => 'product-listing', 'slot' => 'content'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'products' => $products,
        ];

        $childCategory = [
            ...$homeCategory,
            'id' => $this->ids->create('category'),
            'parentId' => $homeCategory['id'],
            'type' => 'page',
            'cmsPage' => [
                ...$homeCategory['cmsPage'],
                'id' => $this->ids->create('cms-page'),
            ],
        ];

        $folderData = $childCategory;
        $folderData['id'] = $this->ids->create('folder');
        $folderData['type'] = 'folder';
        unset($folderData['cmsPage']);

        $linkData = $childCategory;
        $linkData['id'] = $this->ids->create('link');
        $linkData['type'] = 'link';
        unset($linkData['cmsPage']);

        $this->getContainer()->get('category.repository')
            ->create([$homeCategory, $childCategory, $folderData, $linkData], Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'navigationCategoryId' => $this->ids->get('home-category'),
        ]);

        $this->setVisibilities();
    }

    private function setVisibilities(): void
    {
        $products = [];
        for ($i = 0; $i < 5; ++$i) {
            $products[] = [
                'id' => $this->ids->get('product' . $i),
                'visibilities' => [
                    ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ];
        }

        $this->getContainer()->get('product.repository')
            ->update($products, Context::createDefaultContext());
    }
}
