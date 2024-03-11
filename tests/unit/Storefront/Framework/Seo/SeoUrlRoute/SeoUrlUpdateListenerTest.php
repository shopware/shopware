<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\SeoUrlRoute;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Event\CategoryIndexerEvent;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlUpdateListener;

/**
 * @internal
 */
#[CoversClass(SeoUrlUpdateListener::class)]
class SeoUrlUpdateListenerTest extends TestCase
{
    private SeoUrlUpdater&MockObject $seoUrlUpdater;

    private Connection&MockObject $connection;

    private EntityIndexerRegistry&MockObject $indexerRegistry;

    private SeoUrlUpdateListener $listener;

    protected function setUp(): void
    {
        $this->seoUrlUpdater = $this->createMock(SeoUrlUpdater::class);
        $this->connection = $this->createMock(Connection::class);
        $this->indexerRegistry = $this->createMock(EntityIndexerRegistry::class);
        $this->listener = new SeoUrlUpdateListener($this->seoUrlUpdater, $this->connection, $this->indexerRegistry);
    }

    public function testUpdateCategoryUrlsWithFullIndexing(): void
    {
        $childUuid = Uuid::randomHex();
        $event = new CategoryIndexerEvent([$childUuid], Context::createDefaultContext(), [], true);

        $this->connection->expects(static::never())->method('createQueryBuilder');
        $this->seoUrlUpdater->expects(static::once())
            ->method('update')
            ->with(
                NavigationPageSeoUrlRoute::ROUTE_NAME,
                [$childUuid]
            );

        $this->listener->updateCategoryUrls($event);
    }

    public function testUpdateCategoryUrlsWithPartialIndexing(): void
    {
        $childUuid = Uuid::randomHex();
        $parentUuid = Uuid::randomHex();

        $childId1 = Uuid::randomBytes();
        $childId2 = Uuid::randomBytes();

        $event = new CategoryIndexerEvent([$childUuid, $parentUuid], Context::createDefaultContext(), [], false);

        $result = $this->createMock(Result::class);
        $result->method('fetchFirstColumn')->willReturn([$childId1, $childId2]);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('executeQuery')->willReturn($result);
        $this->connection->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->seoUrlUpdater->expects(static::once())
            ->method('update')
            ->with(
                NavigationPageSeoUrlRoute::ROUTE_NAME,
                [$childUuid, $parentUuid, Uuid::fromBytesToHex($childId1), Uuid::fromBytesToHex($childId2)]
            );

        $this->listener->updateCategoryUrls($event);
    }
}
