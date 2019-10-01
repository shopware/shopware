<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer;

abstract class WarmUpMessage
{
    /**
     * @var string
     */
    protected $domain;

    /**
     * @var array|null
     */
    protected $offset;

    /**
     * @var array
     */
    protected $ids;

    public function __construct(string $domain, array $ids, ?array $offset = null)
    {
        $this->domain = $domain;
        $this->offset = $offset;
        $this->ids = $ids;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getOffset(): ?array
    {
        return $this->offset;
    }

    public function getIds(): array
    {
        return $this->ids;
    }
}
