<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult;

class ValueCountResult extends AbstractAggregationResult
{
    /**
     * @var ValueCountItem[]
     */
    protected $values;

    public function __construct(?array $key, array $values)
    {
        parent::__construct($key);
        $this->values = $values;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function add(ValueCountItem $value): void
    {
        $this->values[] = $value;
    }

    public function get($key): ?ValueCountItem
    {
        foreach ($this->values as $value) {
            if ($value->getKey() === $key) {
                return $value;
            }
        }

        return null;
    }
}
