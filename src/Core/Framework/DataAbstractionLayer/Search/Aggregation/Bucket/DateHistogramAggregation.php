<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('core')]
class DateHistogramAggregation extends BucketAggregation
{
    final public const PER_MINUTE = 'minute';
    final public const PER_HOUR = 'hour';
    final public const PER_DAY = 'day';
    final public const PER_WEEK = 'week';
    final public const PER_MONTH = 'month';
    final public const PER_QUARTER = 'quarter';
    final public const PER_YEAR = 'year';

    /**
     * @var list<self::PER_*>
     */
    final public const ALLOWED_INTERVALS = [
        self::PER_MINUTE,
        self::PER_HOUR,
        self::PER_DAY,
        self::PER_WEEK,
        self::PER_MONTH,
        self::PER_QUARTER,
        self::PER_YEAR,
    ];

    /**
     * @var self::PER_*
     */
    protected readonly string $interval;

    /**
     * @param self::PER_* $interval
     */
    public function __construct(
        string $name,
        string $field,
        string $interval,
        private readonly ?FieldSorting $sorting = null,
        ?Aggregation $aggregation = null,
        private readonly ?string $format = null,
        private readonly ?string $timeZone = null
    ) {
        parent::__construct($name, $field, $aggregation);

        $interval = mb_strtolower($interval);
        if (!\in_array($interval, self::ALLOWED_INTERVALS, true)) {
            throw DataAbstractionLayerException::invalidDateHistogramInterval($interval, self::ALLOWED_INTERVALS);
        }

        if (\is_string($timeZone) && !\in_array($timeZone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)) {
            throw DataAbstractionLayerException::invalidTimeZone($timeZone);
        }

        $this->interval = $interval;
    }

    public function getFormat(): ?string
    {
        return $this->format;
    }

    /**
     * @return self::PER_*
     */
    public function getInterval(): string
    {
        return $this->interval;
    }

    public function getSorting(): ?FieldSorting
    {
        return $this->sorting;
    }

    public function getTimeZone(): ?string
    {
        return $this->timeZone;
    }
}
