<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Cache\CacheInvalidationSubscriber;
use Shopware\Core\Content\ContentSystem\Cache\EntityCacheTagResolver;
use Shopware\Core\Content\ContentSystem\ContentSection;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(CacheInvalidationSubscriber::class)]
class CacheInvalidationSubscriberTest extends TestCase
{
    private CacheInvalidator&MockObject $cacheInvalidator;

    private Connection&Stub $connection;

    private EntityCacheTagResolver&Stub $cacheTagResolver;

    private DefinitionInstanceRegistry&Stub $definitionRegistry;

    private CacheInvalidationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $this->connection = static::createStub(Connection::class);
        $this->cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $this->definitionRegistry = static::createStub(DefinitionInstanceRegistry::class);

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
        $layoutId = Uuid::randomHex();

        $event = $this->createWrittenEvent('content_layout', $layoutId);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([ContentSection::MAIN->buildLayoutTag($layoutId)]);

        ($this->subscriber)($event);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function entityAssignmentProvider(): \Generator
    {
        yield 'product assignment' => ['product_content_layout', 'product-'];
        yield 'category assignment' => ['category_content_layout', 'category-route-'];
        yield 'landing page assignment' => ['landing_page_content_layout', 'landing-page-route-'];
    }

    #[DataProvider('entityAssignmentProvider')]
    #[TestDox('invalidates entity assignment cache tag for $entityName')]
    public function testInvalidatesEntityAssignmentCacheTag(string $entityName, string $tagPrefix): void
    {
        $assignmentId = Uuid::randomHex();
        $entityId = Uuid::randomHex();

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

    /**
     * @return \Generator<string, array{string, ContentSection}>
     */
    public static function sectionLayoutProvider(): \Generator
    {
        yield 'header section' => ['header_content_layout', ContentSection::HEADER];
        yield 'footer section' => ['footer_content_layout', ContentSection::FOOTER];
    }

    #[DataProvider('sectionLayoutProvider')]
    #[TestDox('invalidates $section cache tags when $entityName is written')]
    public function testInvalidatesSectionCacheTag(string $entityName, ContentSection $section): void
    {
        $assignmentId = Uuid::randomHex();
        $layoutId = Uuid::randomHex();

        $event = $this->createWrittenEvent($entityName, $assignmentId);

        $this->connection->method('fetchFirstColumn')
            ->willReturn([$layoutId]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with($section->buildRouteCacheTags($layoutId));

        ($this->subscriber)($event);
    }

    #[TestDox('does nothing when no relevant entities are written')]
    public function testDoesNothingWhenNoRelevantEntitiesWritten(): void
    {
        $event = $this->createWrittenEvent('order', Uuid::randomHex());

        $this->cacheInvalidator->expects($this->never())
            ->method('invalidate');

        ($this->subscriber)($event);
    }

    #[TestDox('skips entity invalidation when no assignment IDs are found in database')]
    public function testSkipsEntityInvalidationWhenNoAssignmentIdsFound(): void
    {
        $event = $this->createWrittenEvent('product_content_layout', Uuid::randomHex());

        $this->connection->method('fetchFirstColumn')
            ->willReturn([]);

        $this->cacheInvalidator->expects($this->never())
            ->method('invalidate');

        ($this->subscriber)($event);
    }

    #[TestDox('filters null cache tags from entity invalidation')]
    public function testFiltersNullCacheTagsFromEntityInvalidation(): void
    {
        $assignmentIdA = Uuid::randomHex();
        $assignmentIdB = Uuid::randomHex();
        $productIdA = Uuid::randomHex();
        $productIdB = Uuid::randomHex();

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
