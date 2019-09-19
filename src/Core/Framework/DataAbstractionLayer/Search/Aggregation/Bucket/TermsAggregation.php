<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class TermsAggregation extends BucketAggregation
{
    /**
     * @var int|null
     */
    protected $limit;

    /**
     * @var FieldSorting|null
     */
    protected $sorting;

    public function __construct(string $name, string $field, ?int $limit = null, ?FieldSorting $sorting = null, ?Aggregation $aggregation = null)
    {
        parent::__construct($name, $field, $aggregation);
        $this->limit = $limit;
        $this->sorting = $sorting;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function getSorting(): ?FieldSorting
    {
        return $this->sorting;
    }

    public function getFields(): array
    {
        $fields = parent::getFields();

        if (!$this->sorting) {
            return $fields;
        }
        foreach ($this->sorting->getFields() as $field) {
            $fields[] = $field;
        }

        return $fields;
    }
}
