<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Util;

class DateHistogramCase
{
    /**
     * @var string
     */
    private $interval;

    /**
     * @var array
     */
    private $buckets;

    /**
     * @var string|null
     */
    private $format;

    public function __construct(string $interval, array $buckets, ?string $format = null)
    {
        $this->interval = $interval;
        $this->buckets = $buckets;
        $this->format = $format;
    }

    public function getInterval(): string
    {
        return $this->interval;
    }

    public function getBuckets(): array
    {
        return $this->buckets;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }
}
