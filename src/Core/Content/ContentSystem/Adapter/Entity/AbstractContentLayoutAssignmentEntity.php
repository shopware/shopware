<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter\Entity;

use Shopware\Core\Content\ContentSystem\Adapter\ParameterBinding\ParameterBinding;
use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * Shared properties for sales channel, content layout,
 * and parameter bindings across content layout assignments.
 *
 * @internal
 */
#[Package('discovery')]
abstract class AbstractContentLayoutAssignmentEntity extends Entity implements ContentLayoutAssignmentInterface
{
    use EntityIdTrait;

    protected ?string $salesChannelId = null;

    protected string $contentLayoutId;

    /**
     * @var array<string, ParameterBinding>|null
     */
    protected ?array $parameterBindings = null;

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

    /**
     * @return array<string, ParameterBinding>|null
     */
    public function getParameterBindings(): ?array
    {
        return $this->parameterBindings;
    }

    /**
     * @param array<string, ParameterBinding>|null $parameterBindings
     */
    public function setParameterBindings(?array $parameterBindings): void
    {
        $this->parameterBindings = $parameterBindings;
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
