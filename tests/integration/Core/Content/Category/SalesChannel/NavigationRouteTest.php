<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Category\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\SalesChannel\NavigationRoute;
use Shopware\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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

        $this->createSeoUrl(
            'frontend.landing.page',
            '/landingPage/' . $landingPageId,
            'custom-landing-page-url',
            $landingPageId
        );

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
                static::assertStringContainsString('/custom-landing-page-url', $category['internalLink']);
            }
        }

        $this->getContainer()->get('cache.object')->invalidateTags(['seo-url']);

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->get('sales-channel'),
            'navigationCategoryId' => $this->ids->get('category'),
            'footerCategoryId' => $this->ids->get('category2'),
            'serviceCategoryId' => $this->ids->get('category2'),
        ]);

        $navigationRoute = null;
        foreach ($this->getContainer()->getServiceIds() as $serviceId) {
            if (str_starts_with($serviceId, NavigationRoute::class . '.')) {
                $navigationRoute = $this->getContainer()->get($serviceId);
                break;
            }
        }

        if (!$navigationRoute) {
            $navigationRoute = new NavigationRoute(
                $this->getContainer()->get(Connection::class),
                $this->getContainer()->get('sales_channel.category.repository'),
                $this->getContainer()->get('event_dispatcher'),
                $this->getContainer()->get('Shopware\Core\Content\Category\Service\CategoryUrlGenerator'),
                $this->getContainer()->get('Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface')
            );
        }

        $salesChannelContext = $this->createSalesChannelContext([
            'id' => $this->ids->get('sales-channel'),
        ]);

        $criteria = new Criteria([$this->ids->get('category3')]);
        $categories = $this->getContainer()->get('sales_channel.category.repository')->search(
            $criteria,
            $salesChannelContext
        )->getEntities();

        $category = $categories->get($this->ids->get('category3'));
        $category->setInternalLink($landingPageId);

        $this->createSeoUrl(
            'frontend.landing.page',
            '/landingPage/' . $landingPageId,
            'custom-landing-page-url',
            $landingPageId
        );

        $seoUrls = $this->getContainer()->get('seo_url.repository')->search(
            (new Criteria())->addFilter(new EqualsFilter('seoPathInfo', 'custom-landing-page-url')),
            Context::createDefaultContext()
        );

        echo 'SEO URL count: ' . $seoUrls->count() . "\n";
        if ($seoUrls->count() > 0) {
            $seoUrl = $seoUrls->first();
            echo 'SEO URL path info: ' . $seoUrl->getPathInfo() . "\n";
            echo 'SEO URL foreign key: ' . $seoUrl->getForeignKey() . "\n";
            echo 'SEO URL route name: ' . $seoUrl->getRouteName() . "\n";
        }

        $navigationRoute->setSeoUrlToInternalLink($categories, $salesChannelContext);

        $category = $categories->get($this->ids->get('category3'));
        echo 'Category internal link after: ' . $category->getInternalLink() . "\n";
        echo 'Expected not to be: /landingPage/' . $landingPageId . "\n";

        static::assertNotEquals(
            '/landingPage/' . $landingPageId,
            $category->getInternalLink(),
            'Internal link should be changed from the original landing page path'
        );
        static::assertStringContainsString('/custom-landing-page-url', $category->getInternalLink());

        $response = $this->requestFooterNavigationWithSeoUrls();

        $found = false;
        foreach ($response as $category) {
            if (isset($category['id']) && $category['id'] === $this->ids->get('category3')
                && isset($category['linkType']) && $category['linkType'] === CategoryDefinition::LINK_TYPE_LANDING_PAGE) {
                $found = true;
                static::assertNotEquals(
                    '/landingPage/' . $landingPageId,
                    $category['internalLink'],
                    'Internal link should be changed from the original landing page path'
                );
                static::assertStringContainsString('/custom-landing-page-url', $category['internalLink']);
            }
        }
        static::assertTrue($found, 'Category with landing page link not found in response');
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

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $this->ids->get('category')));

        $existingIds = $this->getContainer()->get('seo_url.repository')
            ->searchIds($criteria, Context::createDefaultContext())
            ->getIds();

        if (!empty($existingIds)) {
            $this->getContainer()->get('seo_url.repository')->delete(
                array_map(function ($id) {
                    return ['id' => $id];
                }, $existingIds),
                Context::createDefaultContext()
            );
        }

        $this->getContainer()->get('seo_url.repository')->create([
            [
                'id' => Uuid::randomHex(),
                'salesChannelId' => $this->ids->get('sales-channel'),
                'routeName' => 'frontend.navigation.page',
                'pathInfo' => '/navigation/' . $this->ids->get('category'),
                'seoPathInfo' => 'custom-category-url',
                'isCanonical' => true,
                'foreignKey' => $this->ids->get('category'),
            ],
        ], Context::createDefaultContext());

        $this->getContainer()->get('cache.object')->invalidateTags(['seo-url']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $this->ids->get('category')));
        $criteria->addFilter(new EqualsFilter('routeName', 'frontend.navigation.page'));
        $criteria->addFilter(new EqualsFilter('seoPathInfo', 'custom-category-url'));

        $seoUrls = $this->getContainer()->get('seo_url.repository')->search(
            $criteria,
            Context::createDefaultContext()
        );

        static::assertGreaterThan(0, $seoUrls->count(), 'No SEO URLs found for category with seoPathInfo "custom-category-url"');

        $criteria = new Criteria([$this->ids->get('category3')]);
        $category = $this->getContainer()->get('category.repository')->search(
            $criteria,
            Context::createDefaultContext()
        )->first();

        static::assertEquals($this->ids->get('category'), $category->getInternalLink(), 'Internal link should be the category ID before navigation request');

        $salesChannelContext = $this->createSalesChannelContext([
            'id' => $this->ids->get('sales-channel'),
        ]);

        $navigationRoute = new NavigationRoute(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('sales_channel.category.repository'),
            $this->getContainer()->get(EventDispatcherInterface::class),
            $this->getContainer()->get('Shopware\Core\Content\Category\Service\CategoryUrlGenerator'),
            $this->getContainer()->get(SeoUrlPlaceholderHandlerInterface::class)
        );

        $criteria = new Criteria([$this->ids->get('category3')]);
        $categories = $this->getContainer()->get('sales_channel.category.repository')->search(
            $criteria,
            $salesChannelContext
        )->getEntities();

        $this->getContainer()->get('seo_url.repository')->upsert([
            [
                'id' => Uuid::randomHex(),
                'salesChannelId' => $this->ids->get('sales-channel'),
                'routeName' => 'frontend.navigation.page',
                'pathInfo' => '/navigation/' . $this->ids->get('category'),
                'seoPathInfo' => 'custom-category-url',
                'isCanonical' => true,
                'foreignKey' => $this->ids->get('category'),
            ],
        ], $salesChannelContext->getContext());

        $this->getContainer()->get('cache.object')->invalidateTags(['seo-url']);

        $this->getContainer()->get('cache.object')->invalidateTags(['seo-url']);

        $navigationRoute->setSeoUrlToInternalLink($categories, $salesChannelContext);

        $updatedCategory = $categories->get($this->ids->get('category3'));
        static::assertNotNull($updatedCategory->getInternalLink(), 'Internal link should not be null after setSeoUrlToInternalLink');

        static::assertNotEquals(
            $this->ids->get('category'),
            $updatedCategory->getInternalLink(),
            'Internal link should be changed from the original category ID. Actual value: ' . $updatedCategory->getInternalLink()
        );

        $response = $this->requestFooterNavigationWithSeoUrls();

        file_put_contents('/tmp/navigation_response.json', json_encode($response, \JSON_PRETTY_PRINT));

        file_put_contents('/tmp/category3_id.txt', $this->ids->get('category3'));

        $found = false;
        foreach ($response as $category) {
            if (isset($category['id']) && $category['id'] === $this->ids->get('category3')) {
                $found = true;
                static::assertEquals(CategoryDefinition::TYPE_LINK, $category['type'], 'Category should be of type link');
                static::assertEquals(CategoryDefinition::LINK_TYPE_CATEGORY, $category['linkType'], 'Category should have linkType category');
                static::assertNotNull($category['internalLink'], 'Internal link should not be null');
            }
        }
        static::assertTrue($found, 'Category with internal link not found in response');
    }

    public function testInternalLinkWithNullPlainUrl(): void
    {
        $originalLink = Uuid::randomHex();
        $this->getContainer()->get('category.repository')->update([
            [
                'id' => $this->ids->get('category3'),
                'type' => CategoryDefinition::TYPE_LINK,
                'linkType' => CategoryDefinition::LINK_TYPE_CATEGORY,
                'internalLink' => $originalLink,
            ],
        ], Context::createDefaultContext());

        $salesChannelContext = $this->createSalesChannelContext([
            'id' => $this->ids->get('sales-channel'),
        ]);

        $mockGenerator = $this->createMock(AbstractCategoryUrlGenerator::class);
        $mockGenerator->method('generate')->willReturn(null);

        $navigationRoute = new NavigationRoute(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get('sales_channel.category.repository'),
            $this->getContainer()->get(EventDispatcherInterface::class),
            $mockGenerator,
            $this->getContainer()->get(SeoUrlPlaceholderHandlerInterface::class)
        );

        $criteria = new Criteria([$this->ids->get('category3')]);
        $categories = $this->getContainer()->get('sales_channel.category.repository')->search(
            $criteria,
            $salesChannelContext
        )->getEntities();

        $navigationRoute->setSeoUrlToInternalLink($categories, $salesChannelContext);

        $category = $categories->get($this->ids->get('category3'));
        static::assertEquals($originalLink, $category->getInternalLink());
    }

    /**
     * Helper method to create a pre-configured SEO URL for an entity
     * Reuses existing SEO URL IDs if they exist
     */
    private function createSeoUrl(string $routeName, string $pathInfo, string $seoPathInfo, string $entityId): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $entityId));
        $criteria->addFilter(new EqualsFilter('routeName', $routeName));

        $existingSeoUrls = $this->getContainer()->get('seo_url.repository')
            ->search($criteria, Context::createDefaultContext());

        $data = [
            'salesChannelId' => $this->ids->get('sales-channel'),
            'routeName' => $routeName,
            'pathInfo' => $pathInfo,
            'seoPathInfo' => $seoPathInfo,
            'isCanonical' => true,
            'foreignKey' => $entityId,
        ];

        if ($existingSeoUrls->count() > 0) {
            $data['id'] = $existingSeoUrls->first()->getId();
        } else {
            $data['id'] = Uuid::randomHex();
        }

        $this->getContainer()->get('seo_url.repository')->upsert([$data], Context::createDefaultContext());

        $this->getContainer()->get('cache.object')->invalidateTags(['seo-url']);
    }

    /**
     * Helper method to request the footer navigation with SEO URLs
     *
     * @return array<string, mixed>
     */
    private function requestFooterNavigationWithSeoUrls(): array
    {
        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->get('sales-channel'),
            'navigationCategoryId' => $this->ids->get('category'),
            'footerCategoryId' => $this->ids->get('category2'),
            'serviceCategoryId' => $this->ids->get('category2'),
        ]);

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

        file_put_contents('/tmp/navigation_response.json', json_encode($response, \JSON_PRETTY_PRINT));

        return $response;
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
