<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\SalesChannel\NavigationRoute;
use Shopware\Core\Content\Media\Event\MediaIndexerEvent;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Snippet\SnippetDefinition;

/**
 * @internal
 */
#[CoversClass(CacheInvalidationSubscriber::class)]
#[Group('cache')]
class CacheInvalidationSubscriberTest extends TestCase
{
    /**
     * @var CacheInvalidator&MockObject
     */
    private CacheInvalidator $cacheInvalidator;

    /**
     * @var Connection&MockObject
     */
    private Connection $connection;

    protected function setUp(): void
    {
        $this->cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $this->connection = $this->createMock(Connection::class);
    }

    public function testConsidersKeyOfCachedBaseSalesChannelContextFactoryForInvalidatingContext(): void
    {
        $salesChannelId = Uuid::randomHex();

        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with(
                [
                    'context-factory-' . $salesChannelId,
                    'base-context-factory-' . $salesChannelId,
                ],
                true
            );

        $subscriber = new CacheInvalidationSubscriber(
            $cacheInvalidator,
            $this->createMock(Connection::class),
            true
        );

        $subscriber->invalidateContext(new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent(
                    SalesChannelDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $salesChannelId,
                            [],
                            SalesChannelDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    Context::createDefaultContext(),
                ),
            ]),
            [],
        ));
    }

    public function testInvalidateMediaWithoutVariantsWillInvalidateOnlyProducts(): void
    {
        $productId = '123';
        $event = new MediaIndexerEvent([Uuid::randomHex()], Context::createDefaultContext(), []);

        $subscriber = new CacheInvalidationSubscriber(
            $this->cacheInvalidator,
            $this->connection,
            true
        );
        $this->connection->method('fetchAllAssociative')
            ->willReturn([['product_id' => $productId, 'version_id' => null]]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with(
                [
                    EntityCacheKeyGenerator::buildProductTag($productId),
                ],
                false
            );

        $subscriber->invalidateMedia($event);
    }

    public function testInvalidateMediaWithVariantsWillInvalidateProductsAndVariants(): void
    {
        $productId = '123';
        $variants = ['456', '789'];
        $event = new MediaIndexerEvent([Uuid::randomHex()], Context::createDefaultContext(), []);

        $subscriber = new CacheInvalidationSubscriber(
            $this->cacheInvalidator,
            $this->connection,
            true
        );
        $this->connection->method('fetchAllAssociative')
            ->willReturn([
                ['product_id' => $productId, 'variant_id' => $variants[0]],
                ['product_id' => $productId, 'variant_id' => $variants[1]],
            ]);

        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with(
                [
                    EntityCacheKeyGenerator::buildProductTag($productId),
                    EntityCacheKeyGenerator::buildProductTag($variants[0]),
                    EntityCacheKeyGenerator::buildProductTag($variants[1]),
                ],
                false
            );

        $subscriber->invalidateMedia($event);
    }

    public function testInvalidateNavigationRouteWithSalesChannelSettings(): void
    {
        $salesChannelId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $subscriber = new CacheInvalidationSubscriber(
            $this->cacheInvalidator,
            $this->connection,
            true
        );

        // Test when sales channel navigation settings change
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
                new EntityWrittenEvent(
                    SalesChannelDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $salesChannelId,
                            [
                                'navigationCategoryId' => Uuid::randomHex(),
                                'navigationCategoryDepth' => 3,
                            ],
                            SalesChannelDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    $context,
                ),
            ]),
            [],
        );

        $this->cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with([NavigationRoute::ALL_TAG]);

        $subscriber->invalidateNavigationRoute($event);
    }

    public function testInvalidateNavigationRouteWithCategoryStructuralChanges(): void
    {
        $categoryId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $subscriber = new CacheInvalidationSubscriber(
            $this->cacheInvalidator,
            $this->connection,
            true
        );

        // Test when category structural data changes (parentId, visible, active, afterCategoryId)
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
                new EntityWrittenEvent(
                    CategoryDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $categoryId,
                            [
                                'parentId' => Uuid::randomHex(),
                                'visible' => true,
                                'active' => false,
                            ],
                            CategoryDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    $context,
                ),
            ]),
            [],
        );

        $this->cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with([NavigationRoute::ALL_TAG]);

        $subscriber->invalidateNavigationRoute($event);
    }

    public function testInvalidateNavigationRouteWithDeletedCategories(): void
    {
        $categoryId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $subscriber = new CacheInvalidationSubscriber(
            $this->cacheInvalidator,
            $this->connection,
            true
        );

        // Test when categories are deleted
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
                new EntityWrittenEvent(
                    CategoryDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $categoryId,
                            [],
                            CategoryDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_DELETE,
                        ),
                    ],
                    $context,
                ),
            ]),
            [],
        );

        $this->cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with([NavigationRoute::ALL_TAG]);

        $subscriber->invalidateNavigationRoute($event);
    }

    public function testInvalidateNavigationRouteWithCategoryTranslationChanges(): void
    {
        $categoryTranslationId = ['categoryId' => Uuid::randomHex(), 'languageId' => Uuid::randomHex()];
        $context = Context::createDefaultContext();

        $subscriber = new CacheInvalidationSubscriber(
            $this->cacheInvalidator,
            $this->connection,
            true
        );

        // Test when category translation name changes
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
                new EntityWrittenEvent(
                    CategoryTranslationDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $categoryTranslationId,
                            [
                                'name' => 'New Category Name',
                            ],
                            CategoryTranslationDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    $context,
                ),
            ]),
            [],
        );

        $this->cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with([NavigationRoute::ALL_TAG]);

        $subscriber->invalidateNavigationRoute($event);
    }

    public function testInvalidateNavigationRouteWithMultipleTriggers(): void
    {
        $salesChannelId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $subscriber = new CacheInvalidationSubscriber(
            $this->cacheInvalidator,
            $this->connection,
            true
        );

        // Test when both sales channel settings and category data change
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
                new EntityWrittenEvent(
                    SalesChannelDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $salesChannelId,
                            [
                                'footerCategoryId' => Uuid::randomHex(),
                            ],
                            SalesChannelDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    $context,
                ),
                new EntityWrittenEvent(
                    CategoryDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $categoryId,
                            [
                                'active' => true,
                            ],
                            CategoryDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    $context,
                ),
            ]),
            [],
        );

        // Should still only invalidate once with the ALL_TAG when sales channel settings change
        $this->cacheInvalidator
            ->expects($this->once())
            ->method('invalidate')
            ->with([NavigationRoute::ALL_TAG]);

        $subscriber->invalidateNavigationRoute($event);
    }

    public function testInvalidateNavigationRouteWithNoRelevantChanges(): void
    {
        $categoryId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $subscriber = new CacheInvalidationSubscriber(
            $this->cacheInvalidator,
            $this->connection,
            true
        );

        // Test when category data changes that don't affect navigation (e.g., description)
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
                new EntityWrittenEvent(
                    CategoryDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            $categoryId,
                            [
                                'description' => 'New description',
                                'metaTitle' => 'New meta title',
                            ],
                            CategoryDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    $context,
                ),
            ]),
            [],
        );

        // Should not invalidate anything
        $this->cacheInvalidator
            ->expects($this->never())
            ->method('invalidate');

        $subscriber->invalidateNavigationRoute($event);
    }

    public function createSnippetEvent(): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent(
                    SnippetDefinition::ENTITY_NAME,
                    [
                        new EntityWriteResult(
                            Uuid::randomHex(),
                            [
                                'translationKey' => 'test',
                            ],
                            SnippetDefinition::ENTITY_NAME,
                            EntityWriteResult::OPERATION_UPDATE,
                        ),
                    ],
                    Context::createDefaultContext(),
                ),
            ]),
            [],
        );
    }
}
