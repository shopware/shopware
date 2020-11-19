<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class SalesChannelEntryPointsEvent implements ShopwareEvent
{
    /**
     * @var array Array of UUIDs of valid navigation entry points
     */
    protected $navigationIds;

    /**
     * @var ?SalesChannelEntity
     */
    protected $salesChannelEntity;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @param Context
     * @param array $navigationIds Array of UUIDs of valid navigation entry points
     * @param SalesChannelEntity $salesChannelEntity
     */
    public function __construct(
        Context $context,
        array $navigationIds = [],
        SalesChannelEntity $salesChannelEntity = null
    ) {
        $this->context = $context;
        $this->navigationIds = $navigationIds;
        $this->salesChannelEntity = $salesChannelEntity;
    }

    public static function forSalesChannel(
        SalesChannelEntity $salesChannel,
        Context $context
    ): SalesChannelEntryPointsEvent {
        return new self($context, [
            'footer-navigation' => $salesChannel->getFooterCategoryId(),
            'service-navigation' => $salesChannel->getServiceCategoryId(),
            'main-navigation' => $salesChannel->getNavigationCategoryId(),
        ], $salesChannel);
    }

    public static function forSalesChannelContext(
        SalesChannelContext $salesChannelContext
    ): SalesChannelEntryPointsEvent {
        return new self($salesChannelContext->getContext(), [
            'footer-navigation' => $salesChannelContext->getSalesChannel()->getFooterCategoryId(),
            'service-navigation' => $salesChannelContext->getSalesChannel()->getServiceCategoryId(),
            'main-navigation' => $salesChannelContext->getSalesChannel()->getNavigationCategoryId(),
        ], $salesChannelContext->getSalesChannel());
    }

    public function addId(string $alias, string $navigationId): void
    {
        $this->navigationIds[$alias] = $navigationId;
    }

    public function getNavigationIds(): array
    {
        return array_filter($this->navigationIds);
    }

    public function getSalesChannelEntity(): ?SalesChannelEntity
    {
        return $this->salesChannelEntity;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}
