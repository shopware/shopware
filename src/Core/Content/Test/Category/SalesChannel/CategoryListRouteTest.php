<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\SalesChannel\AbstractCategoryRoute;
use Shopware\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;

/**
 * @group store-api
 */
class CategoryListRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var AbstractCategoryRoute
     */
    private $route;

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
        $this->route = $this->getContainer()->get(CategoryRoute::class);

        $this->ids = new TestDataCollection(Context::createDefaultContext());
        $this->getContainer()->get(Connection::class)->executeUpdate('SET FOREIGN_KEY_CHECKS = 0;');
        $this->getContainer()->get(Connection::class)->executeUpdate('DELETE FROM category');
        $this->getContainer()->get(Connection::class)->executeUpdate('SET FOREIGN_KEY_CHECKS = 1;');

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'navigationCategoryId' => $this->ids->get('category'),
        ]);
    }

    public function testFetchAll(): void
    {
        $this->browser->request(
            'GET',
            '/store-api/category',
            [
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(3, $response['total']);
        static::assertCount(3, $response['elements']);
        static::assertSame('category', $response['elements'][0]['apiAlias']);
        static::assertContains('Test', array_column($response['elements'], 'name'));
        static::assertContains('Test2', array_column($response['elements'], 'name'));
        static::assertContains('Test3', array_column($response['elements'], 'name'));
    }

    public function testLimit(): void
    {
        $this->browser->request(
            'GET',
            '/store-api/category?limit=1',
            [
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(1, $response['total']);
        static::assertCount(1, $response['elements']);
    }

    public function testIds(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/category',
            [
                'ids' => [
                    $this->ids->get('category'),
                ],
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(1, $response['total']);
        static::assertCount(1, $response['elements']);
        static::assertSame($this->ids->get('category'), $response['elements'][0]['id']);
    }

    public function testIncludes(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/category',
            [
                'includes' => [
                    'category' => ['id', 'name'],
                ],
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(3, $response['total']);
        static::assertCount(3, $response['elements']);
        static::assertArrayHasKey('id', $response['elements'][0]);
        static::assertArrayHasKey('name', $response['elements'][0]);
        static::assertArrayNotHasKey('parentId', $response['elements'][0]);
    }

    private function createData(): void
    {
        $data = [
            [
                'id' => $this->ids->create('category'),
                'name' => 'Test',
            ],
            [
                'id' => $this->ids->create('category2'),
                'name' => 'Test2',
            ],
            [
                'id' => $this->ids->create('category3'),
                'name' => 'Test3',
            ],
        ];

        $this->getContainer()->get('category.repository')
            ->create($data, $this->ids->context);
    }
}
