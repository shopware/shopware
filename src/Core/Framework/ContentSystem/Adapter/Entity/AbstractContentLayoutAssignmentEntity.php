<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Adapter\Entity;

use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * Shared properties for sales channel and content layout across content layout assignments.
 */
#[Package('framework')]
abstract class AbstractContentLayoutAssignmentEntity extends Entity
{
    use EntityIdTrait;

    protected ?string $salesChannelId = null;

    protected string $contentLayoutId;

    protected ?SalesChannelEntity $salesChannel = null;

    protected ?ContentLayoutEntity $contentLayout = null;

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getContentLayoutId(): string
    {
        return $this->contentLayoutId;
    }

    public function setContentLayoutId(string $contentLayoutId): void
    {
        $this->contentLayoutId = $contentLayoutId;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getContentLayout(): ?ContentLayoutEntity
    {
        return $this->contentLayout;
    }

    public function setContentLayout(?ContentLayoutEntity $contentLayout): void
    {
        $this->contentLayout = $contentLayout;
    }
}
