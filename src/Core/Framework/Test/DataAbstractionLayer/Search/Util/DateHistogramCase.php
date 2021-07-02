<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Util;

class DateHistogramCase
{
    private string $interval;

    private array $buckets;

    private ?string $format;

    private ?string $timeZone;

    public function __construct(string $interval, array $buckets, ?string $format = null, ?string $timeZone = null)
    {
        $this->interval = $interval;
        $this->buckets = $buckets;
        $this->format = $format;
        $this->timeZone = $timeZone;
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

    public function getTimeZone(): ?string
    {
        return $this->timeZone;
    }
}
