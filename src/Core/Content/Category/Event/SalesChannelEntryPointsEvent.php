<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Event;

use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class SalesChannelEntryPointsEvent
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
     * @param array $navigationIds Array of UUIDs of valid navigation entry points
     * @param SalesChannelEntity $salesChannelEntity
     */
    public function __construct(array $navigationIds = [], SalesChannelEntity $salesChannelEntity = null)
    {
        $this->navigationIds = $navigationIds;
        $this->salesChannelEntity = $salesChannelEntity;
    }

    public static function forSalesChannel(SalesChannelEntity $salesChannel): SalesChannelEntryPointsEvent
    {
        return new self([
            'footer-navigation' => $salesChannel->getFooterCategoryId(),
            'service-navigation' => $salesChannel->getServiceCategoryId(),
            'main-navigation' => $salesChannel->getNavigationCategoryId(),
        ], $salesChannel);
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
}
