<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
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
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
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

    #[TestDox('invalidates product cache tag when product_content_layout assignment is written')]
    public function testInvalidatesProductAssignmentCacheTag(): void
    {
        $assignmentId = Uuid::randomHex();
        $productId = Uuid::randomHex();

        $event = $this->createWrittenEvent('product_content_layout', $assignmentId);

        $this->connection->method('fetchFirstColumn')
            ->willReturn([$productId]);

        $this->definitionRegistry->method('get')
            ->willReturn(static::createStub(EntityDefinition::class));

        $this->cacheTagResolver->method('resolve')
            ->willReturn('product-' . $productId);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with(['product-' . $productId]);

        ($this->subscriber)($event);
    }

    #[TestDox('invalidates category cache tag when category_content_layout assignment is written')]
    public function testInvalidatesCategoryAssignmentCacheTag(): void
    {
        $assignmentId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        $event = $this->createWrittenEvent('category_content_layout', $assignmentId);

        $this->connection->method('fetchFirstColumn')
            ->willReturn([$categoryId]);

        $this->definitionRegistry->method('get')
            ->willReturn(static::createStub(EntityDefinition::class));

        $this->cacheTagResolver->method('resolve')
            ->willReturn('category-route-' . $categoryId);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with(['category-route-' . $categoryId]);

        ($this->subscriber)($event);
    }

    #[TestDox('invalidates landing page cache tag when landing_page_content_layout assignment is written')]
    public function testInvalidatesLandingPageAssignmentCacheTag(): void
    {
        $assignmentId = Uuid::randomHex();
        $landingPageId = Uuid::randomHex();

        $event = $this->createWrittenEvent('landing_page_content_layout', $assignmentId);

        $this->connection->method('fetchFirstColumn')
            ->willReturn([$landingPageId]);

        $this->definitionRegistry->method('get')
            ->willReturn(static::createStub(EntityDefinition::class));

        $this->cacheTagResolver->method('resolve')
            ->willReturn('landing-page-route-' . $landingPageId);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with(['landing-page-route-' . $landingPageId]);

        ($this->subscriber)($event);
    }

    #[TestDox('invalidates header section cache tags when header_content_layout is written')]
    public function testInvalidatesHeaderSectionCacheTag(): void
    {
        $assignmentId = Uuid::randomHex();
        $layoutId = Uuid::randomHex();

        $event = $this->createWrittenEvent('header_content_layout', $assignmentId);

        $this->connection->method('fetchFirstColumn')
            ->willReturn([$layoutId]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with(ContentSection::HEADER->buildRouteCacheTags($layoutId));

        ($this->subscriber)($event);
    }

    #[TestDox('invalidates footer section cache tags when footer_content_layout is written')]
    public function testInvalidatesFooterSectionCacheTag(): void
    {
        $assignmentId = Uuid::randomHex();
        $layoutId = Uuid::randomHex();

        $event = $this->createWrittenEvent('footer_content_layout', $assignmentId);

        $this->connection->method('fetchFirstColumn')
            ->willReturn([$layoutId]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with(ContentSection::FOOTER->buildRouteCacheTags($layoutId));

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
