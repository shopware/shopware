<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer;

use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;

class IteratorMessage
{
    /**
     * @var string
     */
    protected $warmerClass;

    /**
     * @var array|null
     */
    protected $offset;

    /**
     * @var SalesChannelDomainEntity
     */
    protected $domain;

    public function __construct(SalesChannelDomainEntity $domain, string $warmerClass, ?array $offset = null)
    {
        $this->warmerClass = $warmerClass;
        $this->offset = $offset;
        $this->domain = $domain;
    }

    public function getDomain(): SalesChannelDomainEntity
    {
        return $this->domain;
    }

    public function getWarmerClass(): string
    {
        return $this->warmerClass;
    }

    public function getOffset(): ?array
    {
        return $this->offset;
    }

    public function setOffset(array $offset): void
    {
        $this->offset = $offset;
    }
}
