<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\Service\ProductReviewCountService;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Event\BeforeDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('business-ops')]
class ProductReviewSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly ProductReviewCountService $productReviewCountService)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'product_review.written' => 'createReview',
            BeforeDeleteEvent::class => 'deleteReview',
        ];
    }

    public function deleteReview(BeforeDeleteEvent $event): void
    {
        $ids = $event->getIds(ProductReviewDefinition::ENTITY_NAME);

        if (empty($ids)) {
            return;
        }

        $this->productReviewCountService->updateReviewCount($ids, true);
    }

    public function createReview(EntityWrittenEvent $reviewEvent): void
    {
        if (
            $reviewEvent->getEntityName() !== ProductReviewDefinition::ENTITY_NAME
            || $reviewEvent->getContext()->getVersionId() !== Defaults::LIVE_VERSION
        ) {
            return;
        }

        $this->productReviewCountService->updateReviewCount($reviewEvent->getIds());
    }
}
