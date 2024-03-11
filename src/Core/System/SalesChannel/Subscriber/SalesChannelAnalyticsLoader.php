<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsEntity;
use Shopware\Storefront\Event\StorefrontRenderEvent;

/**
 * @internal
 */
#[Package('buyers-experience')]
class SalesChannelAnalyticsLoader
{
    public function __construct(
        private readonly EntityRepository $salesChannelAnalyticsRepository,
    ) {
    }

    public function loadAnalytics(StorefrontRenderEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $salesChannel = $salesChannelContext->getSalesChannel();
        $analyticsId = $salesChannel->getAnalyticsId();

        if (empty($analyticsId)) {
            return;
        }

        $criteria = new Criteria([$analyticsId]);
        $criteria->setTitle('sales-channel::load-analytics');

        /** @var SalesChannelAnalyticsEntity|null $analytics */
        $analytics = $this->salesChannelAnalyticsRepository->search($criteria, $salesChannelContext->getContext())->first();

        $event->setParameter('storefrontAnalytics', $analytics);
    }
}
