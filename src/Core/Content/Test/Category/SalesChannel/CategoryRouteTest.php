<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Category\SalesChannel\AbstractCategoryRoute;
use Shopware\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 *
 * @group store-api
 */
class CategoryRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private AbstractCategoryRoute $route;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->route = $this->getContainer()->get(CategoryRoute::class);

        $this->ids = new TestDataCollection();

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'navigationCategoryId' => $this->ids->get('home-category'),
        ]);

        $this->setVisibilities();
    }

    public function testCmsPageResolved(): void
    {
        $this->browser->request(
            'GET',
            '/store-api/category/' . $this->ids->get('category')
        );

        $this->assertCmsPage($this->ids->get('category'), $this->ids->get('cms-page'));
    }

    public function testIncludesConsidered(): void
    {
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

        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $listing = $response['cmsPage']['sections'][0]['blocks'][0]['slots'][0]['data']['listing'];

        static::assertArrayNotHasKey('sortings', $listing);
        static::assertArrayNotHasKey('page', $listing);
        static::assertArrayNotHasKey('limit', $listing);

        static::assertArrayHasKey('manufacturer', $listing['aggregations']);
        $manufacturers = $listing['aggregations']['manufacturer'];

        foreach ($manufacturers['entities'] as $manufacturer) {
            static::assertEquals(['name', 'id', 'apiAlias'], array_keys($manufacturer));
        }

        $products = $listing['elements'];
        foreach ($products as $product) {
            static::assertEquals(['name', 'tax', 'manufacturer', 'id', 'apiAlias'], array_keys($product));
            static::assertEquals(['name', 'id', 'apiAlias'], array_keys($product['tax']));
        }
    }

    public function testHome(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/category/home',
            [
            ]
        );

        $this->assertCmsPage($this->ids->get('home-category'), $this->ids->get('home-cms-page'));
    }

    public function testCategoryOfTypeFolder(): void
    {
        $id = $this->ids->get('folder');
        $this->browser->request(
            'POST',
            '/store-api/category/' . $id,
            [
            ]
        );

        $this->assertError($id);
    }

    public function testCategoryOfTypeLink(): void
    {
        $id = $this->ids->get('link');
        $this->browser->request(
            'POST',
            '/store-api/category/' . $id,
            [
            ]
        );

        $this->assertError($id);
    }

    public function testHomeWithSalesChannelOverride(): void
    {
        /** @var EntityRepository $salesChannelRepository */
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $salesChannelRepository->upsert([[
            'id' => $this->ids->get('sales-channel'),
            'homeCmsPageId' => $this->ids->get('cms-page'),
        ]], Context::createDefaultContext());

        $this->browser->request(
            'POST',
            '/store-api/category/home',
            [
            ]
        );

        $this->assertCmsPage($this->ids->get('home-category'), $this->ids->get('cms-page'));
    }

    private function assertError(string $categoryId): void
    {
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $error = new CategoryNotFoundException($categoryId);
        $expectedError = [
            'status' => (string) $error->getStatusCode(),
            'message' => $error->getMessage(),
        ];

        static::assertSame($expectedError['status'], $response['errors'][0]['status']);
        static::assertSame($expectedError['message'], $response['errors'][0]['detail']);
    }

    private function assertCmsPage(string $categoryId, string $cmsPageId): void
    {
        $response = json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals($categoryId, $response['id']);
        static::assertIsArray($response['cmsPage']);

        static::assertEquals($cmsPageId, $response['cmsPage']['id']);
        static::assertEquals($cmsPageId, $response['cmsPageId']);
        static::assertCount(1, $response['cmsPage']['sections']);

        static::assertCount(1, $response['cmsPage']['sections'][0]['blocks']);

        $block = $response['cmsPage']['sections'][0]['blocks'][0];

        static::assertEquals('product-listing', $block['type']);

        static::assertCount(1, $block['slots']);

        $slot = $block['slots'][0];
        static::assertEquals('product-listing', $slot['type']);

        static::assertArrayHasKey('listing', $slot['data']);

        $listing = $slot['data']['listing'];

        static::assertArrayHasKey('aggregations', $listing);
        static::assertArrayHasKey('elements', $listing);
    }

    private function createData(): void
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
            $products[] = array_merge(
                [
                    'id' => $this->ids->create('product' . $i),
                    'manufacturer' => ['id' => $this->ids->create('manufacturer-' . $i), 'name' => 'test-' . $i],
                    'productNumber' => $this->ids->get('product' . $i),
                ],
                $product
            );
        }

        $homeData = $childData = [
            'id' => $this->ids->create('category'),
            'name' => 'Test',
            'type' => 'folder',
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

        $homeData['id'] = $this->ids->create('home-category');
        $homeData['cmsPage']['id'] = $this->ids->create('home-cms-page');

        $childData['parentId'] = $homeData['id'];
        $childData['type'] = 'page';

        $folderData = $childData;
        $folderData['id'] = $this->ids->create('folder');
        $folderData['type'] = 'folder';
        unset($folderData['cmsPage']);

        $linkData = $childData;
        $linkData['id'] = $this->ids->create('link');
        $linkData['type'] = 'link';
        unset($linkData['cmsPage']);

        $this->getContainer()->get('category.repository')
            ->create([$homeData, $childData, $folderData, $linkData], Context::createDefaultContext());
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
