<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Category\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('store-api')]
class NavigationRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'navigationCategoryId' => $this->ids->get('category'),
            'footerCategoryId' => $this->ids->get('category2'),
            'serviceCategoryId' => $this->ids->get('category2'),
        ]);
    }

    public function testLoadNormal(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/navigation/' . $this->ids->get('category') . '/' . $this->ids->get('category'),
                [
                ]
            );

        $response = json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(2, $response);
        static::assertSame('Toys', $response[0]['name']);
        static::assertSame($this->ids->get('category2'), $response[0]['id']);
        static::assertCount(1, $response[0]['children']);
        static::assertSame($this->ids->get('category3'), $response[0]['children'][0]['id']);
        static::assertSame('Kids', $response[0]['children'][0]['name']);
    }

    public function testLoadFlat(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/navigation/' . $this->ids->get('category') . '/' . $this->ids->get('category') . '?buildTree=false',
                [
                ]
            );

        $response = json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(5, $response);
        static::assertArrayHasKey('name', $response[0]);
        $ids = array_column($response, 'id');
        $names = array_column($response, 'name');

        static::assertContains($this->ids->get('category'), $ids);
        static::assertContains($this->ids->get('category2'), $ids);
        static::assertContains($this->ids->get('category3'), $ids);

        static::assertContains('Root', $names);
        static::assertContains('Toys', $names);
        static::assertContains('Kids', $names);
    }

    public function testLoadFlatPOST(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/navigation/' . $this->ids->get('category') . '/' . $this->ids->get('category'),
                [
                    'buildTree' => false,
                ]
            );

        $response = json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(5, $response);
        static::assertArrayHasKey('name', $response[0]);
        $ids = array_column($response, 'id');
        $names = array_column($response, 'name');

        static::assertContains($this->ids->get('category'), $ids);
        static::assertContains($this->ids->get('category2'), $ids);
        static::assertContains($this->ids->get('category3'), $ids);

        static::assertContains('Root', $names);
        static::assertContains('Toys', $names);
        static::assertContains('Kids', $names);
    }

    public function testLoadVisibleChildrenCount(): void
    {
        foreach ([1, 2] as $depth) {
            $this->browser
                ->request(
                    'POST',
                    '/store-api/navigation/' . $this->ids->get('category') . '/' . $this->ids->get('category'),
                    [
                        'depth' => $depth,
                    ]
                );

            $response = json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);

            static::assertCount(2, $response);
            $ids = array_column($response, 'id');

            static::assertContains($this->ids->get('category2'), $ids);
            static::assertContains($this->ids->get('category4'), $ids);

            foreach ($response as $category) {
                switch ($category['id']) {
                    case $this->ids->get('category2'):
                        static::assertEquals(1, $category['visibleChildCount'], 'Depth: ' . $depth);

                        break;
                    case $this->ids->get('category4'):
                        static::assertEquals(0, $category['visibleChildCount'], 'Depth: ' . $depth);

                        break;
                }
            }
        }
    }

    public function testInvalidId(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/navigation/xxxxx/xxxxxx',
                [
                ]
            );

        $response = json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('FRAMEWORK__INVALID_UUID', $response['errors'][0]['code']);
    }

    public function testLoadMainNavigation(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/navigation/main-navigation/main-navigation',
                [
                ]
            );

        $response = json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(2, $response);
        static::assertSame('Toys', $response[0]['name']);
        static::assertSame($this->ids->get('category2'), $response[0]['id']);
        static::assertCount(1, $response[0]['children']);
        static::assertSame($this->ids->get('category3'), $response[0]['children'][0]['id']);
        static::assertSame('Kids', $response[0]['children'][0]['name']);
    }

    public function testFooterNavigation(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/navigation/footer-navigation/footer-navigation',
                [
                ]
            );

        $response = json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);

        // root is Toys
        static::assertCount(1, $response);
        static::assertSame($this->ids->get('category2'), $response[0]['parentId']);
        static::assertSame($this->ids->get('category3'), $response[0]['id']);
        static::assertSame('Kids', $response[0]['name']);
    }

    public function testServiceMenu(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/navigation/service-navigation/service-navigation',
                [
                ]
            );

        $response = json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);

        // root is Toys
        static::assertCount(1, $response);
        static::assertSame($this->ids->get('category2'), $response[0]['parentId']);
        static::assertSame($this->ids->get('category3'), $response[0]['id']);
        static::assertSame('Kids', $response[0]['name']);
    }

    public function testInclude(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/navigation/service-navigation/service-navigation',
                [
                    'includes' => [
                        'category' => ['name'],
                    ],
                ]
            );

        $response = json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(1, $response);
        static::assertArrayHasKey('name', $response[0]);
        static::assertArrayNotHasKey('id', $response[0]);
    }

    public function testLandingPageInternalLinkHasSeoUrl(): void
    {
        $landingPageId = Uuid::randomHex();
        $this->getContainer()->get('landing_page.repository')->create([
            [
                'id' => $landingPageId,
                'name' => 'Test Landing Page',
                'url' => 'test-landing-page',
                'active' => true,
                'salesChannels' => [
                    ['id' => $this->ids->get('sales-channel')],
                ],
            ],
        ], Context::createDefaultContext());

        $this->getContainer()->get('category.repository')->update([
            [
                'id' => $this->ids->get('category3'),
                'type' => CategoryDefinition::TYPE_LINK,
                'linkType' => CategoryDefinition::LINK_TYPE_LANDING_PAGE,
                'internalLink' => $landingPageId,
            ],
        ], Context::createDefaultContext());

        $response = $this->requestFooterNavigationWithSeoUrls();

        foreach ($response as $category) {
            if ($category['id'] === $this->ids->get('category3') && $category['linkType'] === CategoryDefinition::LINK_TYPE_LANDING_PAGE) {
                static::assertStringContainsString('test-landing-page', $category['internalLink']);
            }
        }

        $this->createSeoUrl(
            'frontend.landing.page',
            '/landingPage/' . $landingPageId,
            'custom-landing-page-url',
            $landingPageId
        );

        $response = $this->requestFooterNavigationWithSeoUrls();

        foreach ($response as $category) {
            if ($category['id'] === $this->ids->get('category3') && $category['linkType'] === CategoryDefinition::LINK_TYPE_LANDING_PAGE) {
                static::assertStringContainsString('/custom-landing-page-url', $category['internalLink']);
            }
        }
    }

    public function testProductInternalLinkHasSeoUrl(): void
    {
        $productId = Uuid::randomHex();
        $productBuilder = new \Shopware\Core\Content\Test\Product\ProductBuilder($this->ids, 'TEST-1234');
        $productBuilder->id = $productId;
        $productBuilder
            ->name('Test Product')
            ->price(15, 10)
            ->visibility($this->ids->get('sales-channel'), ProductVisibilityDefinition::VISIBILITY_ALL)
            ->active(true);

        $productBuilder->write($this->getContainer());

        $this->getContainer()->get('category.repository')->update([
            [
                'id' => $this->ids->get('category4'),
                'type' => CategoryDefinition::TYPE_LINK,
                'linkType' => CategoryDefinition::LINK_TYPE_PRODUCT,
                'internalLink' => $productId,
            ],
        ], Context::createDefaultContext());

        $response = $this->requestFooterNavigationWithSeoUrls();

        foreach ($response as $category) {
            if ($category['id'] === $this->ids->get('category4') && $category['linkType'] === CategoryDefinition::LINK_TYPE_PRODUCT) {
                static::assertStringContainsString('/detail/' . $productId, $category['internalLink']);
            }
        }

        $this->createSeoUrl(
            'frontend.detail.page',
            '/detail/' . $productId,
            'custom-product-url',
            $productId
        );

        $response = $this->requestFooterNavigationWithSeoUrls();

        foreach ($response as $category) {
            if ($category['id'] === $this->ids->get('category4') && $category['linkType'] === CategoryDefinition::LINK_TYPE_PRODUCT) {
                static::assertStringContainsString('/custom-product-url', $category['internalLink']);
            }
        }
    }

    public function testCategoryInternalLinkHasSeoUrl(): void
    {
        $this->getContainer()->get('category.repository')->update([
            [
                'id' => $this->ids->get('category3'),
                'type' => CategoryDefinition::TYPE_LINK,
                'linkType' => CategoryDefinition::LINK_TYPE_CATEGORY,
                'internalLink' => $this->ids->get('category'),
            ],
        ], Context::createDefaultContext());

        $response = $this->requestFooterNavigationWithSeoUrls();

        foreach ($response as $category) {
            if ($category['id'] === $this->ids->get('category3') && $category['linkType'] === CategoryDefinition::LINK_TYPE_CATEGORY) {
                static::assertNotEmpty($category['internalLink']);
            }
        }

        $this->createSeoUrl(
            'frontend.navigation.page',
            '/navigation/' . $this->ids->get('category'),
            'custom-category-url',
            $this->ids->get('category')
        );

        $response = $this->requestFooterNavigationWithSeoUrls();

        foreach ($response as $category) {
            if ($category['id'] === $this->ids->get('category3') && $category['linkType'] === CategoryDefinition::LINK_TYPE_CATEGORY) {
                static::assertStringContainsString('/custom-category-url', $category['internalLink']);
            }
        }
    }
    
    public function testInternalLinkWithNullPlainUrl(): void
    {
        $mockUrlGenerator = $this->createMock(AbstractCategoryUrlGenerator::class);
        $mockUrlGenerator->method('generate')->willReturn(null);
        
        $container = $this->getContainer();
        $container->set('Shopware\Core\Content\Category\Service\CategoryUrlGenerator', $mockUrlGenerator);
        
        $this->getContainer()->get('category.repository')->update([
            [
                'id' => $this->ids->get('category3'),
                'type' => CategoryDefinition::TYPE_LINK,
                'linkType' => CategoryDefinition::LINK_TYPE_CATEGORY,
                'internalLink' => $this->ids->get('category'),
            ],
        ], Context::createDefaultContext());
        
        $originalLink = 'original-link-value';
        $this->getContainer()->get('category.repository')->update([
            [
                'id' => $this->ids->get('category3'),
                'internalLink' => $originalLink,
            ],
        ], Context::createDefaultContext());
        
        $response = $this->requestFooterNavigationWithSeoUrls();
        
        foreach ($response as $category) {
            if ($category['id'] === $this->ids->get('category3') && $category['linkType'] === CategoryDefinition::LINK_TYPE_CATEGORY) {
                static::assertEquals($originalLink, $category['internalLink']);
            }
        }
    }

    /**
     * Helper method to create a pre-configured SEO URL for an entity
     */
    private function createSeoUrl(string $routeName, string $pathInfo, string $seoPathInfo, string $entityId): void
    {
        $this->getContainer()->get('seo_url.repository')->upsert([
            [
                'id' => Uuid::randomHex(),
                'salesChannelId' => $this->ids->get('sales-channel'),
                'routeName' => $routeName,
                'pathInfo' => $pathInfo,
                'seoPathInfo' => $seoPathInfo,
                'isCanonical' => true,
                'foreignKey' => $entityId,
            ],
        ], Context::createDefaultContext());
    }

    /**
     * Helper method to request the footer navigation with SEO URLs
     *
     * @return array<string, mixed>
     */
    private function requestFooterNavigationWithSeoUrls(): array
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/navigation/footer-navigation/footer-navigation',
                [
                    'includes' => [
                        'category' => ['id', 'name', 'type', 'linkType', 'internalLink'],
                    ],
                ],
                [],
                ['HTTP_SW-INCLUDE-SEO-URLS' => 'true']
            );

        return json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    private function createData(): void
    {
        $data = [
            'id' => $this->ids->create('category'),
            'name' => 'Root',
            'children' => [
                [
                    'id' => $this->ids->create('category2'),
                    'name' => 'Toys',
                    'tags' => [
                        [
                            'name' => 'Test-Tag',
                        ],
                    ],
                    'children' => [
                        [
                            'id' => $this->ids->create('category3'),
                            'name' => 'Kids',
                        ],
                    ],
                ],
                [
                    'id' => $this->ids->create('category4'),
                    'name' => 'Sports',
                    'afterCategoryId' => $this->ids->get('category2'),
                    'children' => [
                        [
                            'id' => $this->ids->create('category5'),
                            'name' => 'Invisible Child',
                            'visible' => false,
                        ],
                    ],
                ],
            ],
        ];

        static::getContainer()->get('category.repository')
            ->create([$data], Context::createDefaultContext());
    }

    private function getResponseContent(): string
    {
        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);

        return $content;
    }
}
