<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Util;

/**
 * @internal
 */
class DateHistogramCase
{
    /**
     * @param array<string, int> $buckets
     */
    public function __construct(
        private readonly string $interval,
        private readonly array $buckets,
        private readonly ?string $format = null,
        private readonly ?string $timeZone = null
    ) {
    }

    public function getInterval(): string
    {
        return $this->interval;
    }

    /**
     * @return array<string, int>
     */
    public function getBuckets(): array
    {
        return $this->buckets;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    public function getTimeZone(): ?string
    {
        return $this->timeZone;
    }
}
