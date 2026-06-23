<?php declare(strict_types=1);

namespace Shopware\Storefront\ContentSystem\HeaderContentLayout;

use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignmentEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class HeaderContentLayoutEntity extends AbstractContentLayoutAssignmentEntity
{
    protected ?string $domainId = null;

    protected ?SalesChannelDomainEntity $domain = null;

    public function getDomainId(): ?string
    {
        return $this->domainId;
    }

    public function setDomainId(?string $domainId): void
    {
        $this->domainId = $domainId;
    }

    public function getDomain(): ?SalesChannelDomainEntity
    {
        return $this->domain;
    }

    public function setDomain(?SalesChannelDomainEntity $domain): void
    {
        $this->domain = $domain;
    }
}
