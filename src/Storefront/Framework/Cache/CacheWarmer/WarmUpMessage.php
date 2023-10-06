<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

#[Package('storefront')]
class WarmUpMessage implements AsyncMessageInterface
{
    /**
     * @var string
     */
    protected $domain;

    /**
     * @var string
     */
    protected $cacheId;

    /**
     * @var array|null
     */
    protected $offset;

    /**
     * @var string
     */
    protected $route;

    /**
     * @var array
     */
    protected $parameters;

    /**
     * @internal
     */
    public function __construct(
        string $route,
        array $parameters,
        ?array $offset = null
    ) {
        $this->offset = $offset;
        $this->route = $route;
        $this->parameters = $parameters;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getOffset(): ?array
    {
        return $this->offset;
    }

    public function getCacheId(): string
    {
        return $this->cacheId;
    }

    public function setCacheId(string $cacheId): void
    {
        $this->cacheId = $cacheId;
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
