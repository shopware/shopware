<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Layout\Entity;

use Shopware\Core\Content\ContentSystem\Routing\Entity\ContentRouteEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @final
 */
#[Package('discovery')]
class ContentLayoutAssignmentEntity extends Entity
{
    use EntityIdTrait;

    protected string $routeId;

    protected ?string $entityType = null;

    protected ?string $entityId = null;

    protected ?string $associationPath = null;

    protected ?string $salesChannelId = null;

    protected string $layoutId;

    protected int $priority = 0;

    protected ?ContentRouteEntity $route = null;

    protected ?ContentLayoutEntity $layout = null;

    protected ?SalesChannelEntity $salesChannel = null;

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(?string $entityType): void
    {
        $this->entityType = $entityType;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function setEntityId(?string $entityId): void
    {
        $this->entityId = $entityId;
    }

    public function getAssociationPath(): ?string
    {
        return $this->associationPath;
    }

    public function setAssociationPath(?string $associationPath): void
    {
        $this->associationPath = $associationPath;
    }

    public function getRouteId(): string
    {
        return $this->routeId;
    }

    public function setRouteId(string $routeId): void
    {
        $this->routeId = $routeId;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function getRoute(): ?ContentRouteEntity
    {
        return $this->route;
    }

    public function setRoute(?ContentRouteEntity $route): void
    {
        $this->route = $route;
    }

    public function getLayoutId(): string
    {
        return $this->layoutId;
    }

    public function setLayoutId(string $layoutId): void
    {
        $this->layoutId = $layoutId;
    }

    public function getLayout(): ?ContentLayoutEntity
    {
        return $this->layout;
    }

    public function setLayout(?ContentLayoutEntity $layout): void
    {
        $this->layout = $layout;
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
