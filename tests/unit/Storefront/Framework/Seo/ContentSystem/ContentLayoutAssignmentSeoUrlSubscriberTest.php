<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\ContentSystem;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\ContentSystem\ContentLayoutAssignmentSeoUrlSubscriber;
use Shopware\Storefront\Framework\Seo\ContentSystem\ContentSeoRouteDescriptor;
use Shopware\Storefront\Framework\Seo\ContentSystem\ContentSeoRouteRegistry;

/**
 * @internal
 */
#[CoversClass(ContentLayoutAssignmentSeoUrlSubscriber::class)]
class ContentLayoutAssignmentSeoUrlSubscriberTest extends TestCase
{
    private SeoUrlUpdater&MockObject $seoUrlUpdater;

    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        $this->seoUrlUpdater = $this->createMock(SeoUrlUpdater::class);
        $this->connection = $this->createMock(Connection::class);
    }

    #[TestDox('calls seoUrlUpdater with entity ids fetched from DB when assignment ids exist')]
    public function testInvokeCallsSeoUrlUpdaterWithFetchedEntityIds(): void
    {
        $assignmentId = Uuid::randomHex();
        $entityId = Uuid::randomHex();

        $descriptor = $this->createDescriptor('product', 'product_content_layout', 'product_id', 'frontend.detail.page');
        $registry = new ContentSeoRouteRegistry([$descriptor]);

        $event = $this->createEventWithPrimaryKeys('product_content_layout', [$assignmentId]);
        $this->mockQueryBuilderReturning([$entityId]);

        $this->seoUrlUpdater
            ->expects($this->once())
            ->method('update')
            ->with('frontend.detail.page', [$entityId]);

        $subscriber = new ContentLayoutAssignmentSeoUrlSubscriber($this->seoUrlUpdater, $this->connection, $registry);
        $subscriber($event);
    }

    #[TestDox('processes all registered descriptors and calls seoUrlUpdater for each with ids')]
    public function testInvokeProcessesAllDescriptors(): void
    {
        $productAssignmentId = Uuid::randomHex();
        $categoryAssignmentId = Uuid::randomHex();
        $productEntityId = Uuid::randomHex();
        $categoryEntityId = Uuid::randomHex();

        $productDescriptor = $this->createDescriptor('product_content_layout', 'product_content_layout', 'product_id', 'frontend.detail.page');
        $categoryDescriptor = $this->createDescriptor('category_content_layout', 'category_content_layout', 'category_id', 'frontend.navigation.page');
        $registry = new ContentSeoRouteRegistry([$productDescriptor, $categoryDescriptor]);

        $event = static::createStub(EntityWrittenContainerEvent::class);
        $event->method('getPrimaryKeys')
            ->willReturnCallback(static function (string $entityName) use ($productAssignmentId, $categoryAssignmentId): array {
                return match ($entityName) {
                    'product_content_layout' => [$productAssignmentId],
                    'category_content_layout' => [$categoryAssignmentId],
                    default => [],
                };
            });

        $queryBuilder = static::createStub(QueryBuilder::class);
        $result = static::createStub(Result::class);
        $result->method('fetchFirstColumn')
            ->willReturnOnConsecutiveCalls([$productEntityId], [$categoryEntityId]);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('executeQuery')->willReturn($result);
        $this->connection->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->seoUrlUpdater->expects($this->exactly(2))
            ->method('update')
            ->willReturnCallback(static function (string $routeName, array $ids): void {});

        $subscriber = new ContentLayoutAssignmentSeoUrlSubscriber($this->seoUrlUpdater, $this->connection, $registry);
        $subscriber($event);
    }

    #[TestDox('skips descriptors whose assignment ids are empty and updates only those with ids')]
    public function testInvokeSkipsDescriptorsWithNoPrimaryKeys(): void
    {
        $categoryAssignmentId = Uuid::randomHex();
        $categoryEntityId = Uuid::randomHex();

        $productDescriptor = $this->createDescriptor('product_content_layout', 'product_content_layout', 'product_id', 'frontend.detail.page');
        $categoryDescriptor = $this->createDescriptor('category_content_layout', 'category_content_layout', 'category_id', 'frontend.navigation.page');
        $registry = new ContentSeoRouteRegistry([$productDescriptor, $categoryDescriptor]);

        $event = static::createStub(EntityWrittenContainerEvent::class);
        $event->method('getPrimaryKeys')
            ->willReturnCallback(static function (string $entityName) use ($categoryAssignmentId): array {
                return match ($entityName) {
                    'product_content_layout' => [],
                    'category_content_layout' => [$categoryAssignmentId],
                    default => [],
                };
            });

        $this->mockQueryBuilderReturning([$categoryEntityId]);

        $this->seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with('frontend.navigation.page', [$categoryEntityId]);

        $subscriber = new ContentLayoutAssignmentSeoUrlSubscriber($this->seoUrlUpdater, $this->connection, $registry);
        $subscriber($event);
    }

    #[TestDox('uses correct table and id column from descriptor when querying DB')]
    public function testInvokeUsesCorrectTableAndIdColumnFromDescriptor(): void
    {
        $assignmentId = Uuid::randomHex();
        $entityId = Uuid::randomHex();

        $descriptor = $this->createDescriptor('landing_page_content_layout', 'landing_page_content_layout', 'landing_page_id', 'frontend.landing.page');
        $registry = new ContentSeoRouteRegistry([$descriptor]);

        $event = $this->createEventWithPrimaryKeys('landing_page_content_layout', [$assignmentId]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $result = static::createStub(Result::class);
        $result->method('fetchFirstColumn')->willReturn([$entityId]);

        $queryBuilder->expects($this->once())
            ->method('select')
            ->with('DISTINCT LOWER(HEX(landing_page_id))')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('from')
            ->with('landing_page_content_layout')
            ->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('executeQuery')->willReturn($result);
        $this->connection->method('createQueryBuilder')->willReturn($queryBuilder);

        $this->seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with('frontend.landing.page', [$entityId]);

        $subscriber = new ContentLayoutAssignmentSeoUrlSubscriber($this->seoUrlUpdater, $this->connection, $registry);
        $subscriber($event);
    }

    #[TestDox('skips seoUrlUpdater when no assignment ids exist for the entity')]
    public function testInvokeSkipsUpdateWhenNoPrimaryKeys(): void
    {
        $descriptor = $this->createDescriptor('product', 'product_content_layout', 'product_id', 'frontend.detail.page');
        $registry = new ContentSeoRouteRegistry([$descriptor]);

        $event = $this->createEventWithPrimaryKeys('product_content_layout', []);

        $this->connection->expects($this->never())->method('createQueryBuilder');
        $this->seoUrlUpdater->expects($this->never())->method('update');

        $subscriber = new ContentLayoutAssignmentSeoUrlSubscriber($this->seoUrlUpdater, $this->connection, $registry);
        $subscriber($event);
    }

    #[TestDox('skips seoUrlUpdater when fetched entity ids list is empty')]
    public function testInvokeSkipsUpdateWhenFetchedEntityIdsIsEmpty(): void
    {
        $assignmentId = Uuid::randomHex();

        $descriptor = $this->createDescriptor('product', 'product_content_layout', 'product_id', 'frontend.detail.page');
        $registry = new ContentSeoRouteRegistry([$descriptor]);

        $event = $this->createEventWithPrimaryKeys('product_content_layout', [$assignmentId]);
        $this->mockQueryBuilderReturning([]);

        $this->seoUrlUpdater->expects($this->never())->method('update');

        $subscriber = new ContentLayoutAssignmentSeoUrlSubscriber($this->seoUrlUpdater, $this->connection, $registry);
        $subscriber($event);
    }

    #[TestDox('does nothing when registry is empty')]
    public function testInvokeDoesNothingWhenRegistryIsEmpty(): void
    {
        $registry = new ContentSeoRouteRegistry([]);

        $event = $this->createMock(EntityWrittenContainerEvent::class);
        $event->expects($this->never())->method('getPrimaryKeys');

        $this->connection->expects($this->never())->method('createQueryBuilder');
        $this->seoUrlUpdater->expects($this->never())->method('update');

        $subscriber = new ContentLayoutAssignmentSeoUrlSubscriber($this->seoUrlUpdater, $this->connection, $registry);
        $subscriber($event);
    }

    private function createDescriptor(string $entityType, string $entityName, string $idColumn, string $legacySeoRouteName): ContentSeoRouteDescriptor
    {
        $definition = static::createStub(AbstractContentLayoutAssignableDefinition::class);
        $definition->method('getContentLayoutEntityType')->willReturn($entityType);
        $definition->method('getEntityName')->willReturn($entityName);
        $definition->method('getContentLayoutEntityIdColumn')->willReturn($idColumn);

        return new ContentSeoRouteDescriptor($definition, $legacySeoRouteName);
    }

    /**
     * @param list<string> $primaryKeys
     */
    private function createEventWithPrimaryKeys(string $entityName, array $primaryKeys): EntityWrittenContainerEvent&Stub
    {
        $event = static::createStub(EntityWrittenContainerEvent::class);
        $event->method('getPrimaryKeys')
            ->willReturnCallback(static function (string $name) use ($entityName, $primaryKeys): array {
                return $name === $entityName ? $primaryKeys : [];
            });

        return $event;
    }

    /**
     * @param list<string> $returnedIds
     */
    private function mockQueryBuilderReturning(array $returnedIds): void
    {
        $queryBuilder = static::createStub(QueryBuilder::class);
        $result = static::createStub(Result::class);
        $result->method('fetchFirstColumn')->willReturn($returnedIds);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('executeQuery')->willReturn($result);
        $this->connection->method('createQueryBuilder')->willReturn($queryBuilder);
    }
}
