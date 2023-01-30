<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 *
 * @group store-api
 */
class ProductListRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'navigationCategoryId' => $this->ids->get('category'),
        ]);

        $this->setVisibilities();
    }

    public function testListingProducts(): void
    {
        $this->browser->request(
            'GET',
            '/store-api/product',
            [
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(15, $response['total']);
        static::assertCount(15, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
    }

    public function testFetchingTranslations(): void
    {
        $this->browser->request(
            'GET',
            '/store-api/product',
            [
                'ids' => [$this->ids->get('product1')],
                'associations' => ['translations' => []],
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('elements', $response);
        static::assertCount(1, $response['elements']);
        static::assertArrayHasKey('translations', $response['elements'][0]);
        static::assertCount(2, $response['elements'][0]['translations']);

        $languages = \array_column($response['elements'][0]['translations'], 'languageId');
        static::assertContains(Defaults::LANGUAGE_SYSTEM, $languages);
        static::assertContains($this->ids->get('language'), $languages);

        $names = \array_column($response['elements'][0]['translations'], 'name');
        static::assertContains('Test-Product', $names);
        static::assertContains('Other translation', $names);
    }

    /**
     * @group slow
     */
    public function testListingProductsLimit(): void
    {
        $this->browser->request(
            'GET',
            '/store-api/product?limit=1',
            [
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $response['total']);
        static::assertCount(1, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
    }

    public function testListingProductsIds(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/product',
            [
                'ids' => [
                    $this->ids->get('product1'),
                ],
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $response['total']);
        static::assertCount(1, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
    }

    public function testListingProductsIncludes(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/product',
            [
                'includes' => [
                    'product' => ['id'],
                ],
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(15, $response['total']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
        static::assertArrayHasKey('id', $response['elements'][0]);
        static::assertArrayHasKey('apiAlias', $response['elements'][0]);
        static::assertArrayNotHasKey('name', $response['elements'][0]);
    }

    public function testListingProductsIncludesOnlyPublicReviews(): void
    {
        $product = (new ProductBuilder($this->ids, 'p1'))
            ->visibility($this->ids->get('sales-channel'))
            ->price(10)
            ->review('test public review', 'this is a public review', 3, $this->ids->get('sales-channel'))
            ->review('test hidden review', 'this is a hidden review', 0, $this->ids->get('sales-channel'), Defaults::LANGUAGE_SYSTEM, false)
            ->build();

        $this->getContainer()->get('product.repository')
            ->upsert([$product], Context::createDefaultContext());

        $this->browser->request(
            'POST',
            '/store-api/product',
            [
                'filter' => [[
                    'type' => 'equals',
                    'field' => 'productNumber',
                    'value' => 'p1',
                ]],
                'associations' => [
                    'productReviews' => [],
                ],
            ],
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $response['total']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
        static::assertArrayHasKey('id', $response['elements'][0]);
        static::assertArrayHasKey('productReviews', $response['elements'][0]);
        $reviews = $response['elements'][0]['productReviews'];
        static::assertCount(1, $reviews);
        static::assertSame('test public review', $reviews[0]['title']);
    }

    public function testListingProductsIncludesOwnInactiveReviews(): void
    {
        $customerId = $this->login($this->browser);

        $product = (new ProductBuilder($this->ids, 'p1'))
            ->visibility($this->ids->get('sales-channel'))
            ->price(10)
            ->review('test public review', 'this is a public review', 3, $this->ids->get('sales-channel'))
            ->review('test hidden own review', 'this is a hidden review', 0, $this->ids->get('sales-channel'), Defaults::LANGUAGE_SYSTEM, false, $customerId)
            ->build();

        $this->getContainer()->get('product.repository')
            ->upsert([$product], Context::createDefaultContext());

        $this->browser->request(
            'POST',
            '/store-api/product',
            [
                'filter' => [[
                    'type' => 'equals',
                    'field' => 'productNumber',
                    'value' => 'p1',
                ]],
                'associations' => [
                    'productReviews' => [
                        'sort' => [['field' => 'points', 'order' => FieldSorting::DESCENDING]],
                    ],
                ],
            ],
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $response['total']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
        static::assertArrayHasKey('id', $response['elements'][0]);
        static::assertArrayHasKey('productReviews', $response['elements'][0]);
        $reviews = $response['elements'][0]['productReviews'];
        static::assertCount(2, $reviews);
        static::assertSame('test public review', $reviews[0]['title']);
        static::assertSame('test hidden own review', $reviews[1]['title']);
    }

    private function createData(): void
    {
        $products = [];

        $this->getContainer()->get('language.repository')->create(
            [
                [
                    'id' => $this->ids->create('language'),
                    'name' => 'foo',
                    'localeId' => $this->getLocaleIdOfSystemLanguage(),
                    'translationCode' => [
                        'code' => Uuid::randomHex(),
                        'name' => 'Test locale',
                        'territory' => 'test',
                    ],
                    'salesChannels' => [
                        ['id' => TestDefaults::SALES_CHANNEL],
                    ],
                ],
            ],
            Context::createDefaultContext()
        );

        for ($i = 0; $i < 15; ++$i) {
            $products[] = (new ProductBuilder($this->ids, 'product' . $i))
                ->name('Test-Product')
                ->stock(10)
                ->price(15)
                ->translation($this->ids->create('language'), 'name', 'Other translation')
                ->manufacturer('manufacturer-' . $i)
                ->build();
        }

        $data = [
            'id' => $this->ids->create('category'),
            'name' => 'Test',
            'cmsPage' => [
                'id' => $this->ids->create('cms-page'),
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

        $this->getContainer()->get('category.repository')
            ->create([$data], Context::createDefaultContext());
    }

    private function setVisibilities(): void
    {
        $products = [];
        for ($i = 0; $i < 15; ++$i) {
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
