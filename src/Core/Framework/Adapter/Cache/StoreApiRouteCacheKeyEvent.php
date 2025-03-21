<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('framework')]
/**
 * @deprecated tag:v6.8.0 - Will be removed in 6.8.0 as it was not used anymore
 */
class StoreApiRouteCacheKeyEvent extends Event
{
    private bool $disableCaching = false;

    /**
     * @param array<mixed> $parts
     */
    public function __construct(
        protected array $parts,
        protected Request $request,
        protected SalesChannelContext $context,
        protected ?Criteria $criteria
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );
    }

    /**
     * @return array<mixed>
     */
    public function getParts(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        return $this->parts;
    }

    public function getRequest(): Request
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        return $this->request;
    }

    public function getContext(): SalesChannelContext
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        return $this->context;
    }

    public function getCriteria(): ?Criteria
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        return $this->criteria;
    }

    /**
     * @param array<int, bool|string> $parts
     */
    public function setParts(array $parts): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        $this->parts = $parts;
    }

    public function addPart(string $part): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        $this->parts[] = $part;
    }

    public function getSalesChannelId(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        return $this->context->getSalesChannelId();
    }

    public function disableCaching(): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        $this->disableCaching = true;
    }

    public function shouldCache(): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        return !$this->disableCaching;
    }
}
