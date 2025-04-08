<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Category\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
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

    /**
     * @return array<string, array{linkType: string, categoryId: string, targetId: ?string, expectedUrlPart: string, createEntity: bool, preConfiguredSeoUrl: bool}>
     */
    public static function internalLinkSeoUrlDataProvider(): array
    {
        return [
            'landing_page without pre-configured SEO URL' => [
                'linkType' => CategoryDefinition::LINK_TYPE_LANDING_PAGE,
                'categoryId' => 'category3',
                'targetId' => null, // Will be generated in test
                'expectedUrlPart' => '/test-landing-page',
                'createEntity' => true,
                'preConfiguredSeoUrl' => false,
            ],
            'landing_page with pre-configured SEO URL' => [
                'linkType' => CategoryDefinition::LINK_TYPE_LANDING_PAGE,
                'categoryId' => 'category3',
                'targetId' => null, // Will be generated in test
                'expectedUrlPart' => '/custom-landing-page-url',
                'createEntity' => true,
                'preConfiguredSeoUrl' => true,
            ],
            'product without pre-configured SEO URL' => [
                'linkType' => CategoryDefinition::LINK_TYPE_PRODUCT,
                'categoryId' => 'category4',
                'targetId' => null, // Will be generated in test
                'expectedUrlPart' => '/detail/',
                'createEntity' => true,
                'preConfiguredSeoUrl' => false,
            ],
            'product with pre-configured SEO URL' => [
                'linkType' => CategoryDefinition::LINK_TYPE_PRODUCT,
                'categoryId' => 'category4',
                'targetId' => null, // Will be generated in test
                'expectedUrlPart' => '/custom-product-url',
                'createEntity' => true,
                'preConfiguredSeoUrl' => true,
            ],
            'category without pre-configured SEO URL' => [
                'linkType' => CategoryDefinition::LINK_TYPE_CATEGORY,
                'categoryId' => 'category3',
                'targetId' => 'category',
                'expectedUrlPart' => '',
                'createEntity' => false,
                'preConfiguredSeoUrl' => false,
            ],
            'category with pre-configured SEO URL' => [
                'linkType' => CategoryDefinition::LINK_TYPE_CATEGORY,
                'categoryId' => 'category3',
                'targetId' => 'category',
                'expectedUrlPart' => '/custom-category-url',
                'createEntity' => false,
                'preConfiguredSeoUrl' => true,
            ],
        ];
    }

    /**
     * @param string $linkType Type of internal link (landing_page, product, category)
     * @param string $categoryId ID of the category to update with the internal link
     * @param string|null $targetId ID of the target entity (or null if it should be generated)
     * @param string $expectedUrlPart Expected part of the SEO URL
     * @param bool $createEntity Whether to create a new entity or use an existing one
     * @param bool $preConfiguredSeoUrl Whether to pre-configure a custom SEO URL
     */
    #[DataProvider('internalLinkSeoUrlDataProvider')]
    public function testInternalLinkHasSeoUrl(
        string $linkType,
        string $categoryId,
        ?string $targetId,
        string $expectedUrlPart,
        bool $createEntity,
        bool $preConfiguredSeoUrl
    ): void {
        $entityId = $targetId ? $this->ids->get($targetId) : Uuid::randomHex();
        
        if ($createEntity) {
            if ($linkType === CategoryDefinition::LINK_TYPE_LANDING_PAGE) {
                $this->getContainer()->get('landing_page.repository')->create([
                    [
                        'id' => $entityId,
                        'name' => 'Test Landing Page',
                        'url' => 'test-landing-page',
                        'active' => true,
                        'salesChannels' => [
                            ['id' => $this->ids->get('sales-channel')],
                        ],
                    ],
                ], Context::createDefaultContext());
            } elseif ($linkType === CategoryDefinition::LINK_TYPE_PRODUCT) {
                $productBuilder = new \Shopware\Core\Content\Test\Product\ProductBuilder($this->ids, 'TEST-1234');
                $productBuilder->id = $entityId;
                $productBuilder
                    ->name('Test Product')
                    ->price(15, 10)
                    ->visibility($this->ids->get('sales-channel'), ProductVisibilityDefinition::VISIBILITY_ALL)
                    ->active(true);
                
                $productBuilder->write($this->getContainer());
            }
        }
        
        if ($preConfiguredSeoUrl) {
            $routeName = '';
            $pathInfo = '';
            
            if ($linkType === CategoryDefinition::LINK_TYPE_LANDING_PAGE) {
                $routeName = 'frontend.landing.page';
                $pathInfo = '/landingPage/' . $entityId;
            } elseif ($linkType === CategoryDefinition::LINK_TYPE_PRODUCT) {
                $routeName = 'frontend.detail.page';
                $pathInfo = '/detail/' . $entityId;
            } elseif ($linkType === CategoryDefinition::LINK_TYPE_CATEGORY) {
                $routeName = 'frontend.navigation.page';
                $pathInfo = '/navigation/' . $entityId;
            }
            
            $this->getContainer()->get('seo_url.repository')->create([
                [
                    'id' => Uuid::randomHex(),
                    'salesChannelId' => $this->ids->get('sales-channel'),
                    'routeName' => $routeName,
                    'pathInfo' => $pathInfo,
                    'seoPathInfo' => 'custom-' . $linkType . '-url',
                    'isCanonical' => true,
                    'foreignKey' => $entityId,
                ],
            ], Context::createDefaultContext());
        }

        $this->getContainer()->get('category.repository')->update([
            [
                'id' => $this->ids->get($categoryId),
                'type' => CategoryDefinition::TYPE_LINK,
                'linkType' => $linkType,
                'internalLink' => $entityId,
            ],
        ], Context::createDefaultContext());

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

        $response = json_decode($this->getResponseContent(), true, 512, \JSON_THROW_ON_ERROR);

        foreach ($response as $category) {
            if ($category['id'] === $this->ids->get($categoryId) && $category['linkType'] === $linkType) {
                if ($preConfiguredSeoUrl) {
                    static::assertStringContainsString($expectedUrlPart, $category['internalLink']);
                } else {
                    if ($expectedUrlPart) {
                        static::assertStringContainsString($expectedUrlPart, $category['internalLink']);
                    } else {
                        static::assertNotEmpty($category['internalLink']);
                    }
                }
            }
        }
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
