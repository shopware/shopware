<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Sitemap\Provider;

use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Sitemap\Provider\CategoryUrlProvider;
use Shopware\Core\Content\Sitemap\Service\ConfigHandler;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CategoryUrlProvider::class)]
class CategoryUrlProviderTest extends TestCase
{
    private readonly ConfigHandler&MockObject $configHandler;

    private readonly Connection&MockObject $connection;

    private readonly CategoryDefinition&MockObject $definition;

    private readonly IteratorFactory&MockObject $iteratorFactory;

    private readonly RouterInterface&MockObject $router;

    private readonly IdsCollection $ids;

    protected function setUp(): void
    {
        $this->configHandler = $this->createMock(ConfigHandler::class);
        $this->connection = $this->createMock(Connection::class);
        $this->definition = $this->createMock(CategoryDefinition::class);
        $this->iteratorFactory = $this->createMock(IteratorFactory::class);
        $this->router = $this->createMock(RouterInterface::class);
        $this->ids = new IdsCollection();

        $this->initServices();
    }

    public function testGetDecorated(): void
    {
        static::expectException(DecorationPatternException::class);
        $this->getCategoryUrlProvider()->getDecorated();
    }

    public function testGetName(): void
    {
        $name = $this->getCategoryUrlProvider()->getName();
        static::assertSame('category', $name);
    }

    public function testGetCategoryUrls(): void
    {
        $context = Generator::generateSalesChannelContext();

        $provider = $this->getCategoryUrlProvider();
        $urlResult = $provider->getUrls($context, 100, 50);

        $urls = $urlResult->getUrls();
        static::assertCount(2, $urls);

        $url = array_shift($urls);
        static::assertInstanceOf(Url::class, $url);
        static::assertSame($this->ids->get('category-1'), $url->getIdentifier());
        static::assertSame('category/1/detail', $url->getLoc());

        $url = array_shift($urls);
        static::assertInstanceOf(Url::class, $url);
        static::assertSame($this->ids->get('category-2'), $url->getIdentifier());
        static::assertSame('category/2/detail', $url->getLoc());
    }

    private function initServices(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'foreign_key' => $this->ids->get('category-1'),
                'seo_path_info' => 'category/1/detail',
            ],
        ]);

        $this->router->method('generate')->willReturn('category/2/detail');

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('executeQuery')->willReturn(new Result(
            new ArrayResult(
                [
                    'increment',
                    'id',
                    'created_at',
                    'updated_at',
                ],
                [
                    [
                        1,
                        $this->ids->get('category-1'),
                        '2021-01-01 00:00:00',
                        null,
                    ],
                    [
                        2,
                        $this->ids->get('category-2'),
                        '2021-01-01 00:00:00',
                        null,
                    ],
                ]
            ),
            $this->connection
        ));

        $query = $this->createMock(IterableQuery::class);
        $query->method('getQuery')->willReturn($queryBuilder);

        $this->iteratorFactory->method('createIterator')
            ->willReturn($query);

        $this->configHandler->method('get')->willReturn([
            [
                'resource' => CategoryEntity::class,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'identifier' => $this->ids->get('category-1'),
            ],
            [
                'resource' => CategoryEntity::class,
                'salesChannelId' => Uuid::randomHex(),
                'identifier' => $this->ids->get('category-2'),
            ],
            [
                'resource' => ProductEntity::class,
                'salesChannelId' => Uuid::randomHex(),
                'identifier' => $this->ids->get('product-3'),
            ],
        ]);
    }

    private function getCategoryUrlProvider(): CategoryUrlProvider
    {
        return new CategoryUrlProvider(
            $this->configHandler,
            $this->connection,
            $this->definition,
            $this->iteratorFactory,
            $this->router
        );
    }
}
