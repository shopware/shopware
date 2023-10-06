<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-ignore-next-line cannot be final, as it is extended, also designed to be used directly
 */
#[Package('core')]
class BucketAggregation extends Aggregation
{
    public function __construct(
        string $name,
        string $field,
        protected ?Aggregation $aggregation
    ) {
        parent::__construct($name, $field);
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
