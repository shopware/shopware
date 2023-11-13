<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Service\ProductReviewCountService;
use Shopware\Core\Checkout\Customer\Subscriber\ProductReviewSubscriber;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Customer\Subscriber\ProductReviewSubscriber
 */
#[Package('business-ops')]
class ProductReviewSubscriberTest extends TestCase
{
    private MockObject&ProductReviewCountService $productReviewCountService;

    private ProductReviewSubscriber $productReviewSubscriber;

    protected function setUp(): void
    {
        $this->productReviewCountService = $this->createMock(ProductReviewCountService::class);
        $this->productReviewSubscriber = new ProductReviewSubscriber($this->productReviewCountService);
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertEquals([
            'product_review.written' => 'createReview',
            EntityDeleteEvent::class => 'detectChangeset',
            'product_review.deleted' => 'onReviewDeleted',
        ], $this->productReviewSubscriber->getSubscribedEvents());
    }

    public function testDetectChangesetWithReviewDeleteEvent(): void
    {
        $event = EntityDeleteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new DeleteCommand(
                    new ProductReviewDefinition(),
                    [
                        'id' => 'foo',
                    ],
                    new EntityExistence(ProductReviewDefinition::ENTITY_NAME, ['id' => 'foo'], true, false, false, [])
                ),
            ]
        );

        foreach ($event->getCommands() as $command) {
            static::assertInstanceOf(ChangeSetAware::class, $command);
            static::assertFalse($command->requiresChangeSet());
        }

        $this->productReviewSubscriber->detectChangeset($event);

        foreach ($event->getCommands() as $command) {
            static::assertInstanceOf(ChangeSetAware::class, $command);
            static::assertTrue($command->requiresChangeSet());
        }
    }

    public function testDetectChangesetWithInvalidCommands(): void
    {
        $event = EntityDeleteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new DeleteCommand(
                    new ProductDefinition(),
                    [
                        'id' => 'foo',
                    ],
                    new EntityExistence(ProductDefinition::ENTITY_NAME, ['id' => 'foo'], true, false, false, [])
                ),
                new InsertCommand(
                    new ProductReviewDefinition(),
                    ['id' => 'foo'],
                    ['id' => 'foo'],
                    new EntityExistence(ProductReviewDefinition::ENTITY_NAME, ['id' => 'foo'], true, false, false, []),
                    '/bar'
                ),
            ]
        );

        foreach ($event->getCommands() as $command) {
            static::assertInstanceOf(ChangeSetAware::class, $command);
            static::assertFalse($command->requiresChangeSet());
        }

        $this->productReviewSubscriber->detectChangeset($event);

        foreach ($event->getCommands() as $command) {
            static::assertInstanceOf(ChangeSetAware::class, $command);
            static::assertFalse($command->requiresChangeSet());
        }
    }

    public function testOnReviewDeleted(): void
    {
        $event = new EntityDeletedEvent(
            ProductReviewDefinition::ENTITY_NAME,
            [
                new EntityWriteResult(
                    'id',
                    ['id' => 'id'],
                    ProductReviewDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_DELETE,
                    new EntityExistence(ProductReviewDefinition::ENTITY_NAME, ['id' => 'id'], true, false, false, []),
                    new ChangeSet(['customer_id' => 'customer_id'], [], true)
                ),
                // should not trigger update as it has empty changeset
                new EntityWriteResult(
                    'id',
                    ['id' => 'id'],
                    ProductReviewDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_DELETE,
                    new EntityExistence(ProductReviewDefinition::ENTITY_NAME, ['id' => 'id'], true, false, false, []),
                    new ChangeSet([], [], true)
                ),
                // should not trigger update as it has wrong entity
                new EntityWriteResult(
                    'id',
                    ['id' => 'id'],
                    ProductDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_DELETE,
                    new EntityExistence(ProductDefinition::ENTITY_NAME, ['id' => 'id'], true, false, false, []),
                    new ChangeSet(['customer_id' => 'customer_id'], [], true)
                ),
            ],
            Context::createDefaultContext(),
        );

        $this->productReviewCountService->expects(static::once())
            ->method('updateReviewCountForCustomer')
            ->with('customer_id');

        $this->productReviewSubscriber->onReviewDeleted($event);
    }

    public function testCreateReviewWithInvalidEntityName(): void
    {
        $ids = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];
        $this->productReviewCountService->expects(static::never())->method('updateReviewCount');
        $this->productReviewSubscriber->createReview($this->getEntityWrittenEvent($ids, true));
    }

    public function testCreateReview(): void
    {
        $ids = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];
        $this->productReviewCountService->expects(static::once())->method('updateReviewCount')->with($ids);

        $this->productReviewSubscriber->createReview($this->getEntityWrittenEvent($ids));
    }

    /**
     * @param string[] $ids
     */
    private function getEntityWrittenEvent(array $ids = [], bool $invalidEntity = false): EntityWrittenEvent
    {
        $entity = $invalidEntity ? ProductDefinition::ENTITY_NAME : ProductReviewDefinition::ENTITY_NAME;

        $writtenResults = [];
        foreach ($ids as $id) {
            $writtenResult = $this->createMock(EntityWriteResult::class);
            $writtenResult->method('getPrimaryKey')->willReturn($id);
            $writtenResults[] = $writtenResult;
        }

        return new EntityWrittenEvent($entity, $writtenResults, Context::createDefaultContext());
    }
}
