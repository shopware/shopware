<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Adapter\Entity\HeaderContentLayout;

use Shopware\Core\Content\ContentSystem\Adapter\Entity\ContentLayoutAssignmentInterface;
use Shopware\Core\Content\ContentSystem\Adapter\ParameterBinding\ParameterBinding;
use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class HeaderContentLayoutEntity extends Entity implements ContentLayoutAssignmentInterface
{
    use EntityIdTrait;

    protected ?string $domainId = null;

    protected ?string $salesChannelId = null;

    protected string $contentLayoutId;

    /**
     * @var array<string, ParameterBinding>|null
     */
    protected ?array $parameterBindings = null;

    protected ?SalesChannelDomainEntity $domain = null;

    protected ?SalesChannelEntity $salesChannel = null;

    protected ?ContentLayoutEntity $contentLayout = null;

    /**
     * @api
     */
    public function getDomainId(): ?string
    {
        return $this->domainId;
    }

    /**
     * @api
     */
    public function setDomainId(?string $domainId): void
    {
        $this->domainId = $domainId;
    }

    /**
     * @api
     */
    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    /**
     * @api
     */
    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    /**
     * @api
     */
    public function getContentLayoutId(): string
    {
        return $this->contentLayoutId;
    }

    /**
     * @api
     */
    public function setContentLayoutId(string $contentLayoutId): void
    {
        $this->contentLayoutId = $contentLayoutId;
    }

    /**
     * @api
     *
     * @return array<string, ParameterBinding>|null
     */
    public function getParameterBindings(): ?array
    {
        return $this->parameterBindings;
    }

    /**
     * @api
     *
     * @param array<string, ParameterBinding>|null $parameterBindings
     */
    public function setParameterBindings(?array $parameterBindings): void
    {
        $this->parameterBindings = $parameterBindings;
    }

    /**
     * @api
     */
    public function getDomain(): ?SalesChannelDomainEntity
    {
        return $this->domain;
    }

    /**
     * @api
     */
    public function setDomain(?SalesChannelDomainEntity $domain): void
    {
        $this->domain = $domain;
    }

    /**
     * @api
     */
    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    /**
     * @api
     */
    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    /**
     * @api
     */
    public function getContentLayout(): ?ContentLayoutEntity
    {
        return $this->contentLayout;
    }

    /**
     * @api
     */
    public function setContentLayout(?ContentLayoutEntity $contentLayout): void
    {
        $this->contentLayout = $contentLayout;
    }
}
