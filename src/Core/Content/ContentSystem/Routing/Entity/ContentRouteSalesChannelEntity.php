<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Routing\Entity;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @final
 */
#[Package('discovery')]
class ContentRouteSalesChannelEntity extends Entity
{
    use EntityIdTrait;

    protected string $contentRouteId;

    protected string $salesChannelId;

    protected ?ContentRouteEntity $contentRoute = null;

    protected ?SalesChannelEntity $salesChannel = null;

    public function getContentRouteId(): string
    {
        return $this->contentRouteId;
    }

    public function setContentRouteId(string $contentRouteId): void
    {
        $this->contentRouteId = $contentRouteId;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getContentRoute(): ?ContentRouteEntity
    {
        return $this->contentRoute;
    }

    public function setContentRoute(?ContentRouteEntity $contentRoute): void
    {
        $this->contentRoute = $contentRoute;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }
}
