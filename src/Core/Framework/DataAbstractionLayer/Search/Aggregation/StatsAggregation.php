<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation;

use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\Struct\Struct;

class StatsAggregation extends Struct implements Aggregation
{
    use AggregationTrait;

    /**
     * @var bool
     */
    protected $count;

    /**
     * @var bool
     */
    protected $avg;

    /**
     * @var bool
     */
    protected $min;

    /**
     * @var bool
     */
    protected $max;

    /**
     * @var bool
     */
    protected $sum;

    public function __construct(string $field, string $name, bool $count = true, bool $avg = true, bool $sum = true, bool $min = true, bool $max = true)
    {
        $this->field = $field;
        $this->name = $name;
        $this->count = $count;
        $this->avg = $avg;
        $this->min = $min;
        $this->max = $max;
        $this->sum = $sum;
    }

    public function fetchCount(): bool
    {
        return $this->count;
    }

    public function fetchAvg(): bool
    {
        return $this->avg;
    }

    public function fetchMin(): bool
    {
        return $this->min;
    }

    public function fetchMax(): bool
    {
        return $this->max;
    }

    public function fetchSum(): bool
    {
        return $this->sum;
    }

    public function isFieldSupported(Field $field): bool
    {
        if ($field instanceof IntField || $field instanceof FloatField || $field instanceof PriceField) {
            return true;
        }

        return false;
    }
}
