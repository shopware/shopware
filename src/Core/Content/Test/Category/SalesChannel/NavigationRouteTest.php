<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;

/**
 * @group store-api
 */
class NavigationRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

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

            $response = json_decode($this->browser->getResponse()->getContent(), true);

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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

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

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertCount(1, $response);
        static::assertArrayHasKey('name', $response[0]);
        static::assertArrayNotHasKey('id', $response[0]);
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

        $this->getContainer()->get('category.repository')
            ->create([$data], $this->ids->context);
    }
}
