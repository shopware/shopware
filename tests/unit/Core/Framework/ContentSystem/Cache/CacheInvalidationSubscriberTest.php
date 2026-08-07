<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\ContentSystem\Cache\CacheInvalidationSubscriber;
use Shopware\Core\Framework\ContentSystem\Cache\EntityCacheTagResolver;
use Shopware\Core\Framework\ContentSystem\ContentSection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CacheInvalidationSubscriber::class)]
class CacheInvalidationSubscriberTest extends TestCase
{
    private CacheInvalidator&MockObject $cacheInvalidator;

    private Connection&Stub $connection;

    private EntityCacheTagResolver&Stub $cacheTagResolver;

    private DefinitionInstanceRegistry&Stub $definitionRegistry;

    private CacheInvalidationSubscriber $subscriber;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->cacheInvalidator = static::createMock(CacheInvalidator::class);
        $this->connection = static::createStub(Connection::class);
        $this->cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $this->definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $this->ids = new IdsCollection();

        $this->subscriber = new CacheInvalidationSubscriber(
            $this->cacheInvalidator,
            $this->connection,
            $this->cacheTagResolver,
            $this->definitionRegistry,
        );
    }

    #[TestDox('invalidates layout cache tag when content_layout entity is written')]
    public function testInvalidatesLayoutCacheTagOnContentLayoutWrite(): void
    {
        $layoutId = 'layout-id';

        $event = $this->createWrittenEvent('content_layout', $layoutId);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([ContentSection::MAIN->buildLayoutTag($layoutId)]);

        ($this->subscriber)($event);
    }

    #[DataProvider('invalidatesEntityAssignmentCacheTagProvider')]
    #[TestDox('invalidates entity assignment cache tag for $entityName')]
    public function testInvalidatesEntityAssignmentCacheTag(string $entityName, string $tagPrefix): void
    {
        $assignmentId = $this->ids->get('assignment');
        $entityId = 'entity-id';

        $event = $this->createWrittenEvent($entityName, $assignmentId);

        $this->connection->method('fetchFirstColumn')
            ->willReturn([$entityId]);

        $this->definitionRegistry->method('get')
            ->willReturn(static::createStub(EntityDefinition::class));

        $this->cacheTagResolver->method('resolve')
            ->willReturn($tagPrefix . $entityId);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([$tagPrefix . $entityId]);

        ($this->subscriber)($event);
    }

    #[DataProvider('invalidatesSectionCacheTagProvider')]
    #[TestDox('invalidates $section cache tags when $entityName is written')]
    public function testInvalidatesSectionCacheTag(string $entityName, ContentSection $section): void
    {
        $assignmentId = $this->ids->get('assignment');
        $layoutId = 'layout-id';

        $event = $this->createWrittenEvent($entityName, $assignmentId);

        $this->connection->method('fetchFirstColumn')
            ->willReturn([$layoutId]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with($section->buildRouteCacheTags($layoutId));

        ($this->subscriber)($event);
    }

    #[TestDox('filters null cache tags from entity invalidation')]
    public function testFiltersNullCacheTagsFromEntityInvalidation(): void
    {
        $assignmentIdA = $this->ids->get('assignment-a');
        $assignmentIdB = $this->ids->get('assignment-b');
        $productIdA = 'entity-a';
        $productIdB = 'entity-b';

        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent('product_content_layout', [
                    new EntityWriteResult($assignmentIdA, [], 'product_content_layout', EntityWriteResult::OPERATION_INSERT),
                    new EntityWriteResult($assignmentIdB, [], 'product_content_layout', EntityWriteResult::OPERATION_INSERT),
                ], Context::createDefaultContext()),
            ]),
            [],
        );

        $this->connection->method('fetchFirstColumn')
            ->willReturn([$productIdA, $productIdB]);

        $this->definitionRegistry->method('get')
            ->willReturn(static::createStub(EntityDefinition::class));

        $this->cacheTagResolver->method('resolve')
            ->willReturnCallback(fn (EntityDefinition $def, string $id) => match ($id) {
                $productIdA => 'product-' . $productIdA,
                default => null,
            });

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with(['product-' . $productIdA]);

        ($this->subscriber)($event);
    }

    #[TestDox('skips section invalidation when no layout IDs are found in database')]
    public function testSkipsSectionInvalidationWhenNoLayoutIdsFound(): void
    {
        $event = $this->createWrittenEvent('header_content_layout', $this->ids->get('assignment'));

        $this->connection->method('fetchFirstColumn')
            ->willReturn([]);

        $this->cacheInvalidator->expects($this->never())
            ->method('invalidate');

        ($this->subscriber)($event);
    }

    #[TestDox('skips cache invalidation when no relevant entities are written')]
    public function testSkipsCacheInvalidationWhenNoRelevantEntitiesWritten(): void
    {
        $event = $this->createWrittenEvent('order', 'order-id');

        $this->cacheInvalidator->expects($this->never())
            ->method('invalidate');

        ($this->subscriber)($event);
    }

    #[TestDox('skips entity invalidation when no assignment IDs are found in database')]
    public function testSkipsEntityInvalidationWhenNoAssignmentIdsFound(): void
    {
        $event = $this->createWrittenEvent('product_content_layout', $this->ids->get('assignment'));

        $this->connection->method('fetchFirstColumn')
            ->willReturn([]);

        $this->cacheInvalidator->expects($this->never())
            ->method('invalidate');

        ($this->subscriber)($event);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function invalidatesEntityAssignmentCacheTagProvider(): \Generator
    {
        yield 'product assignment' => ['product_content_layout', 'product-'];
        yield 'category assignment' => ['category_content_layout', 'category-route-'];
        yield 'landing page assignment' => ['landing_page_content_layout', 'landing-page-route-'];
    }

    /**
     * @return \Generator<string, array{string, ContentSection}>
     */
    public static function invalidatesSectionCacheTagProvider(): \Generator
    {
        yield 'header section' => ['header_content_layout', ContentSection::HEADER];
        yield 'footer section' => ['footer_content_layout', ContentSection::FOOTER];
    }

    private function createWrittenEvent(string $entityName, string $id): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent($entityName, [
                    new EntityWriteResult($id, [], $entityName, EntityWriteResult::OPERATION_INSERT),
                ], Context::createDefaultContext()),
            ]),
            [],
        );
    }
}
