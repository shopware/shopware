<?php declare(strict_types=1);

namespace Shopware\Storefront\System\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\StorefrontRenderEvent;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelAnalyticsLoader
{
    /**
     * @param EntityRepository<SalesChannelAnalyticsCollection> $salesChannelAnalyticsRepository
     */
    public function __construct(
        private readonly EntityRepository $salesChannelAnalyticsRepository,
    ) {
    }

    public function loadAnalytics(StorefrontRenderEvent $event): void
    {
        $salesChannelContext = $event->getSalesChannelContext();
        $analyticsId = $this->getAnalyticsId($salesChannelContext);
        if ($analyticsId === null) {
            return;
        }

        $event->setParameter('storefrontAnalytics', $this->load($analyticsId, $salesChannelContext));
    }

    /**
     * Whether the sales channel tracks with analytics. A sales channel without analytics is answered
     * without a query.
     */
    public function isActive(SalesChannelContext $salesChannelContext): bool
    {
        $analyticsId = $this->getAnalyticsId($salesChannelContext);
        if ($analyticsId === null) {
            return false;
        }

        return $this->load($analyticsId, $salesChannelContext)?->isActive() ?? false;
    }

    private function getAnalyticsId(SalesChannelContext $salesChannelContext): ?string
    {
        $analyticsId = $salesChannelContext->getSalesChannel()->getAnalyticsId();

        return $analyticsId === null || $analyticsId === '' ? null : $analyticsId;
    }

    private function load(string $analyticsId, SalesChannelContext $salesChannelContext): ?SalesChannelAnalyticsEntity
    {
        $criteria = new Criteria([$analyticsId]);
        $criteria->setTitle('sales-channel::load-analytics');

        return $this->salesChannelAnalyticsRepository->search($criteria, $salesChannelContext->getContext())->getEntities()->first();
    }
}
