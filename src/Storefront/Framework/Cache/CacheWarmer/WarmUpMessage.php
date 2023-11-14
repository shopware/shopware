<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache\CacheWarmer;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @deprecated tag:v6.6.0 - Will be removed, use site crawlers for real cache warming
 */
#[Package('core')]
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
     * @var array<mixed>|null
     */
    protected $offset;

    /**
     * @var string
     */
    protected $route;

    /**
     * @var array<mixed>
     */
    protected $parameters;

    /**
     * @internal
     *
     * @param array<mixed>|null $offset
     * @param array<mixed> $parameters
     */
    public function __construct(
        string $route,
        array $parameters,
        ?array $offset = null
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0')
        );

        $this->offset = $offset;
        $this->route = $route;
        $this->parameters = $parameters;
    }

    public function getDomain(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0')
        );

        return $this->domain;
    }

    /**
     * @return mixed[]|null
     */
    public function getOffset(): ?array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0')
        );

        return $this->offset;
    }

    public function getCacheId(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0')
        );

        return $this->cacheId;
    }

    public function setCacheId(string $cacheId): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0')
        );

        $this->cacheId = $cacheId;
    }

    public function setDomain(string $domain): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0')
        );

        $this->domain = $domain;
    }

    public function getRoute(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0')
        );

        return $this->route;
    }

    /**
     * @return mixed[]
     */
    public function getParameters(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0')
        );

        return $this->parameters;
    }
}
