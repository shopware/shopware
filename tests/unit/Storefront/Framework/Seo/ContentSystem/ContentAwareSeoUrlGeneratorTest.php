<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\ContentSystem;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Framework\Routing\StorefrontContentRouteLoader;
use Shopware\Storefront\Framework\Seo\ContentSystem\ContentAwareSeoUrlGenerator;
use Shopware\Storefront\Framework\Seo\ContentSystem\ContentSeoRouteDescriptor;
use Shopware\Storefront\Framework\Seo\ContentSystem\ContentSeoRouteRegistry;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(ContentAwareSeoUrlGenerator::class)]
class ContentAwareSeoUrlGeneratorTest extends TestCase
{
    private SeoUrlGenerator&MockObject $inner;

    private Connection&MockObject $connection;

    private RouterInterface&MockObject $router;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inner = $this->createMock(SeoUrlGenerator::class);
        $this->connection = $this->createMock(Connection::class);
        $this->router = $this->createMock(RouterInterface::class);
    }

    #[TestDox('delegates to inner generator when no descriptor found for entity type')]
    public function testGenerateDelegatesToInnerWhenNoDescriptor(): void
    {
        $ids = [Uuid::randomHex()];
        $template = '{{ product.name }}';
        $context = Context::createDefaultContext();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $route = $this->createSeoUrlRoute('product');
        $registry = new ContentSeoRouteRegistry([]);

        $generator = new ContentAwareSeoUrlGenerator(
            $this->inner,
            $this->connection,
            $this->router,
            $registry,
        );

        $seoUrl = new SeoUrlEntity();
        $seoUrl->setId(Uuid::randomHex());

        $this->inner->method('generate')
            ->with($ids, $template, $route, $context, $salesChannel)
            ->willReturn([$seoUrl]);

        $result = iterator_to_array($generator->generate($ids, $template, $route, $context, $salesChannel));

        static::assertCount(1, $result);
        static::assertSame($seoUrl, $result[0]);
    }

    #[TestDox('yields all seo urls unmodified when none match assigned entity ids')]
    public function testGenerateYieldsAllUrlsWhenNoneAssigned(): void
    {
        $entityId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $ids = [$entityId];

        $context = Context::createDefaultContext();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);

        $descriptor = $this->createDescriptor('product', 'product_content_layout', 'product_id');
        $registry = new ContentSeoRouteRegistry([$descriptor]);
        $route = $this->createSeoUrlRoute('product');

        $generator = new ContentAwareSeoUrlGenerator(
            $this->inner,
            $this->connection,
            $this->router,
            $registry,
        );

        $this->mockQueryBuilderFetchingRows([], $salesChannelId);

        $seoUrl = new SeoUrlEntity();
        $seoUrl->setId(Uuid::randomHex());
        $seoUrl->setForeignKey($entityId);
        $seoUrl->setPathInfo('/legacy-path/' . $entityId);

        $this->inner->method('generate')->willReturn([$seoUrl]);
        $this->router->expects($this->never())->method('generate');

        $result = iterator_to_array($generator->generate($ids, '', $route, $context, $salesChannel));

        static::assertCount(1, $result);
        static::assertSame('/legacy-path/' . $entityId, $result[0]->getPathInfo());
    }

    #[TestDox('rewrites pathInfo for seo urls whose foreign key is in assigned entity ids')]
    public function testGenerateRewritesPathInfoForAssignedEntities(): void
    {
        $entityId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $ids = [$entityId];

        $context = Context::createDefaultContext();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);

        $descriptor = $this->createDescriptor('product', 'product_content_layout', 'product_id');
        $registry = new ContentSeoRouteRegistry([$descriptor]);
        $route = $this->createSeoUrlRoute('product');

        $generator = new ContentAwareSeoUrlGenerator(
            $this->inner,
            $this->connection,
            $this->router,
            $registry,
        );

        $this->mockQueryBuilderFetchingRows([$entityId], $salesChannelId);

        $seoUrl = new SeoUrlEntity();
        $seoUrl->setId(Uuid::randomHex());
        $seoUrl->setForeignKey($entityId);
        $seoUrl->setPathInfo('/old-path');

        $this->inner->method('generate')->willReturn([$seoUrl]);

        $expectedRouteName = StorefrontContentRouteLoader::buildRouteName('product');
        $this->router->method('generate')
            ->with($expectedRouteName, [StorefrontContentRouteLoader::PARAMETER_ENTITY_ID => $entityId])
            ->willReturn('/content/products/' . $entityId);

        $result = iterator_to_array($generator->generate($ids, '', $route, $context, $salesChannel));

        static::assertCount(1, $result);
        static::assertSame('/content/products/' . $entityId, $result[0]->getPathInfo());
    }

    #[TestDox('yields all seo urls including both rewritten and non-rewritten ones')]
    public function testGenerateYieldsAllUrlsIncludingMixed(): void
    {
        $assignedId = Uuid::randomHex();
        $unassignedId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $ids = [$assignedId, $unassignedId];

        $context = Context::createDefaultContext();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);

        $descriptor = $this->createDescriptor('category', 'category_content_layout', 'category_id');
        $registry = new ContentSeoRouteRegistry([$descriptor]);
        $route = $this->createSeoUrlRoute('category');

        $generator = new ContentAwareSeoUrlGenerator(
            $this->inner,
            $this->connection,
            $this->router,
            $registry,
        );

        $this->mockQueryBuilderFetchingRows([$assignedId], $salesChannelId);

        $assignedSeoUrl = new SeoUrlEntity();
        $assignedSeoUrl->setId(Uuid::randomHex());
        $assignedSeoUrl->setForeignKey($assignedId);
        $assignedSeoUrl->setPathInfo('/legacy-assigned');

        $unassignedSeoUrl = new SeoUrlEntity();
        $unassignedSeoUrl->setId(Uuid::randomHex());
        $unassignedSeoUrl->setForeignKey($unassignedId);
        $unassignedSeoUrl->setPathInfo('/legacy-unassigned');

        $this->inner->method('generate')->willReturn([$assignedSeoUrl, $unassignedSeoUrl]);

        $expectedRouteName = StorefrontContentRouteLoader::buildRouteName('category');
        $this->router->method('generate')
            ->with($expectedRouteName, [StorefrontContentRouteLoader::PARAMETER_ENTITY_ID => $assignedId])
            ->willReturn('/content/categories/' . $assignedId);

        $result = iterator_to_array($generator->generate($ids, '', $route, $context, $salesChannel));

        static::assertCount(2, $result);
        static::assertSame('/content/categories/' . $assignedId, $result[0]->getPathInfo());
        static::assertSame('/legacy-unassigned', $result[1]->getPathInfo());
    }

    #[TestDox('filters out non-string ids before querying assigned entity ids')]
    public function testGenerateFiltersNonStringIdsBeforeQuery(): void
    {
        $entityId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $ids = [$entityId, ['id' => Uuid::randomHex(), 'versionId' => Uuid::randomHex()]];

        $context = Context::createDefaultContext();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);

        $descriptor = $this->createDescriptor('product', 'product_content_layout', 'product_id');
        $registry = new ContentSeoRouteRegistry([$descriptor]);
        $route = $this->createSeoUrlRoute('product');

        $generator = new ContentAwareSeoUrlGenerator(
            $this->inner,
            $this->connection,
            $this->router,
            $registry,
        );

        $this->connection->expects($this->once())->method('createQueryBuilder')
            ->willReturn($this->createQueryBuilderMock([], $salesChannelId));

        $this->inner->method('generate')->willReturn([]);

        iterator_to_array($generator->generate($ids, '', $route, $context, $salesChannel));
    }

    #[TestDox('skips DB query when no string ids are present in the ids list')]
    public function testGenerateSkipsQueryWhenNoStringIds(): void
    {
        $salesChannelId = Uuid::randomHex();
        $ids = [['id' => Uuid::randomHex(), 'versionId' => Uuid::randomHex()]];

        $context = Context::createDefaultContext();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);

        $descriptor = $this->createDescriptor('product', 'product_content_layout', 'product_id');
        $registry = new ContentSeoRouteRegistry([$descriptor]);
        $route = $this->createSeoUrlRoute('product');

        $generator = new ContentAwareSeoUrlGenerator(
            $this->inner,
            $this->connection,
            $this->router,
            $registry,
        );

        $this->connection->expects($this->never())->method('createQueryBuilder');
        $this->inner->method('generate')->willReturn([]);

        iterator_to_array($generator->generate($ids, '', $route, $context, $salesChannel));
    }

    #[TestDox('passes correct table and id column from descriptor to DB query')]
    public function testGenerateUsesDescriptorTableAndColumnInQuery(): void
    {
        $entityId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();

        $context = Context::createDefaultContext();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);

        $descriptor = $this->createDescriptor('landing_page', 'landing_page_content_layout', 'landing_page_id');
        $registry = new ContentSeoRouteRegistry([$descriptor]);
        $route = $this->createSeoUrlRoute('landing_page');

        $generator = new ContentAwareSeoUrlGenerator(
            $this->inner,
            $this->connection,
            $this->router,
            $registry,
        );

        $result = static::createStub(Result::class);
        $result->method('fetchFirstColumn')->willReturn([]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('select')
            ->with('DISTINCT LOWER(HEX(landing_page_id))')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())->method('from')
            ->with('landing_page_content_layout')
            ->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('executeQuery')->willReturn($result);
        $this->connection->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->inner->method('generate')->willReturn([]);

        iterator_to_array($generator->generate([$entityId], '', $route, $context, $salesChannel));
    }

    private function createSeoUrlRoute(string $entityName): SeoUrlRouteInterface
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        $config = static::createStub(SeoUrlRouteConfig::class);
        $config->method('getDefinition')->willReturn($definition);

        $route = static::createStub(SeoUrlRouteInterface::class);
        $route->method('getConfig')->willReturn($config);

        return $route;
    }

    private function createDescriptor(string $entityType, string $entityName, string $idColumn): ContentSeoRouteDescriptor
    {
        $definition = static::createStub(AbstractContentLayoutAssignableDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);
        $definition->method('getContentLayoutEntityType')->willReturn($entityType);
        $definition->method('getContentLayoutEntityIdColumn')->willReturn($idColumn);

        return new ContentSeoRouteDescriptor($definition, 'frontend.legacy.' . $entityType);
    }

    /**
     * @param list<string> $rows
     */
    private function mockQueryBuilderFetchingRows(array $rows, string $salesChannelId): void
    {
        $queryBuilder = $this->createQueryBuilderMock($rows, $salesChannelId);
        $this->connection->method('createQueryBuilder')->willReturn($queryBuilder);
    }

    /**
     * @param list<string> $rows
     */
    private function createQueryBuilderMock(array $rows, string $salesChannelId): QueryBuilder&MockObject
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchFirstColumn')->willReturn($rows);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('executeQuery')->willReturn($result);

        return $queryBuilder;
    }
}
