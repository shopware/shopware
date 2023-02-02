<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;

class BucketAggregation extends Aggregation
{
    /**
     * @var Aggregation|null
     */
    protected $aggregation;

    public function __construct(string $name, string $field, ?Aggregation $aggregation)
    {
        parent::__construct($name, $field);
        $this->aggregation = $aggregation;
    }

    public function getFields(): array
    {
        if (!$this->aggregation) {
            return [$this->field];
        }

        $fields = $this->aggregation->getFields();
        $fields[] = $this->field;

        return $fields;
    }

    public function getAggregation(): ?Aggregation
    {
        return $this->aggregation;
    }
}
