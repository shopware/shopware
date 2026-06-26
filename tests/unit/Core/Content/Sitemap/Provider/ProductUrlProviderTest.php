<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Sitemap\Provider;

use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopware\Core\Content\Sitemap\Provider\ProductUrlProvider;
use Shopware\Core\Content\Sitemap\Service\ConfigHandler;
use Shopware\Core\Content\Sitemap\Struct\Url;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductUrlProvider::class)]
class ProductUrlProviderTest extends TestCase
{
    private readonly ConfigHandler&MockObject $configHandler;

    private readonly Connection&MockObject $connection;

    private readonly ProductDefinition&MockObject $definition;

    private readonly IteratorFactory&MockObject $iteratorFactory;

    private readonly EntityRouteResolver&MockObject $entityRouteResolver;

    private readonly SystemConfigService&MockObject $systemConfigService;

    private readonly EventDispatcher&MockObject $dispatcher;

    private ProductUrlProvider $productUrlProvider;

    protected function setUp(): void
    {
        $this->configHandler = $this->createMock(ConfigHandler::class);
        $this->connection = $this->createMock(Connection::class);
        $this->definition = $this->createMock(ProductDefinition::class);
        $this->iteratorFactory = $this->createMock(IteratorFactory::class);
        $this->entityRouteResolver = $this->createMock(EntityRouteResolver::class);
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->dispatcher = $this->createMock(EventDispatcher::class);

        $this->productUrlProvider = new ProductUrlProvider(
            $this->configHandler,
            $this->connection,
            $this->definition,
            $this->iteratorFactory,
            $this->entityRouteResolver,
            $this->systemConfigService,
            $this->dispatcher
        );
    }

    public function testGetDecorated(): void
    {
        static::expectException(DecorationPatternException::class);
        $this->productUrlProvider->getDecorated();
    }

    public function testGetName(): void
    {
        $name = $this->productUrlProvider->getName();
        static::assertSame('product', $name);
    }

    public function testGetProductUrls(): void
    {
        $ids = new IdsCollection();
        $queryResult = new Result(
            new ArrayResult(
                ['auto_increment', 'id', 'created_at', 'updated_at'],
                [
                    [1, $ids->get('product-1'), '2021-01-01 00:00:00', null],
                    [2, $ids->get('product-2'), '2021-01-01 00:00:00', null],
                ]
            ),
            $this->connection
        );

        $this->connection->method('fetchAllAssociative')->willReturn([
            [
                'foreign_key' => $ids->get('product-1'),
                'seo_path_info' => 'product/1/detail',
            ],
        ]);

        $this->entityRouteResolver->method('getRouteNameForEntityName')->willReturn('frontend.detail.page');
        $this->entityRouteResolver->method('generateUrl')->willReturn('product/2/detail');

        $queryBuilderMock = $this->createMock(QueryBuilder::class);
        $queryBuilderMock->method('executeQuery')->willReturn($queryResult);

        $query = $this->createMock(IterableQuery::class);
        $query->method('getQuery')->willReturn($queryBuilderMock);

        $this->iteratorFactory->method('createIterator')->willReturn($query);
        $this->configHandler->method('get')
            ->willReturn([
                [
                    'resource' => ProductEntity::class,
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'identifier' => $ids->get('product-1'),
                ],
                [
                    'resource' => ProductEntity::class,
                    'salesChannelId' => Uuid::randomHex(),
                    'identifier' => $ids->get('product-2'),
                ],
            ]);

        $context = Generator::generateSalesChannelContext();

        $urlResult = $this->productUrlProvider->getUrls($context, 100, 50);

        $urls = $urlResult->getUrls();
        static::assertCount(2, $urls);

        $url = array_shift($urls);
        static::assertInstanceOf(Url::class, $url);
        static::assertSame($ids->get('product-1'), $url->getIdentifier());
        static::assertSame('product/1/detail', $url->getLoc());

        $url = array_shift($urls);
        static::assertInstanceOf(Url::class, $url);
        static::assertSame($ids->get('product-2'), $url->getIdentifier());
        static::assertSame('product/2/detail', $url->getLoc());

        static::assertSame(2, $urlResult->getNextOffset());
    }
}
