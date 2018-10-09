<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Read;

use Shopware\Core\Framework\ORM\Search\Aggregation\Aggregation;
use Shopware\Core\Framework\ORM\Search\Criteria;

class ReadCriteria extends Criteria
{
    /**
     * @var string[]
     */
    protected $ids;

    public function __construct(array $ids)
    {
        if (\count($ids) > \count(array_filter($ids))) {
            throw new \RuntimeException('Inconsistent argument for ReadCriteria. Please filter all invalid values first.');
        }

        $this->ids = $ids;
    }

    public function getIds(): array
    {
        return $this->ids;
    }

    public function addAggregation(Aggregation ...$aggregations): Criteria
    {
        throw new \RuntimeException('Aggregations are not supported in a read request');
    }

    public function resetAggregations(Aggregation ...$aggregations): Criteria
    {
        throw new \RuntimeException('Aggregations are not supported in a read request');
    }
}
