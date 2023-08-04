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
use Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Customer\Subscriber\ProductReviewSubscriber
 */
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
            BeforeDeleteEvent::class => 'deleteReview',
        ], $this->productReviewSubscriber->getSubscribedEvents());
    }

    public function testDeleteReviewsWithoutIds(): void
    {
        $writeContext = WriteContext::createFromContext(Context::createDefaultContext());
        $event = BeforeDeleteEvent::create($writeContext, []);

        $this->productReviewCountService->expects(static::never())->method('updateReviewCount');

        $this->productReviewSubscriber->deleteReview($event);
    }

    public function testDeleteReviews(): void
    {
        $ids = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];
        $this->productReviewCountService->expects(static::once())->method('updateReviewCount')->with($ids, true);

        $this->productReviewSubscriber->deleteReview($this->getBeforeDeleteEvent($ids));
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
        $this->productReviewCountService->expects(static::once())->method('updateReviewCount')->with($ids, false);

        $this->productReviewSubscriber->createReview($this->getEntityWrittenEvent($ids));
    }

    /**
     * @param string[] $ids
     */
    private function getBeforeDeleteEvent(array $ids = []): MockObject&BeforeDeleteEvent
    {
        $event = $this->createMock(BeforeDeleteEvent::class);
        $event
            ->method('getIds')
            ->with(ProductReviewDefinition::ENTITY_NAME)
            ->willReturn($ids);

        return $event;
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
