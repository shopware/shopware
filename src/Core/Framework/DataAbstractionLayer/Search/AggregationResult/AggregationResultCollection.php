<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\StateAwareTrait;

/**
 * @extends Collection<AggregationResult>
 */
#[Package('core')]
class AggregationResultCollection extends Collection
{
    use StateAwareTrait;

    /**
     * @param AggregationResult $result
     */
    public function add($result): void
    {
        $this->set($result->getName(), $result);
    }

    /**
     * @param string|int        $key
     * @param AggregationResult $result
     */
    public function set($key, $result): void
    {
        parent::set($result->getName(), $result);
    }

    public function get($name): ?AggregationResult
    {
        return $this->elements[$name] ?? null;
    }

    public function getApiAlias(): string
    {
        return 'dal_aggregation_result_cache';
    }

    protected function getExpectedClass(): ?string
    {
        return AggregationResult::class;
    }
}
