<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket;

use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-ignore-next-line cannot be final, as it is extended, also designed to be used directly
 */
#[Package('core')]
class BucketResult extends AggregationResult
{
    /**
     * @param list<Bucket> $buckets
     */
    public function __construct(
        string $name,
        protected array $buckets
    ) {
        parent::__construct($name);
    }

    /**
     * @return list<Bucket>
     */
    public function getBuckets(): array
    {
        return $this->buckets;
    }

    public function sort(\Closure $closure): void
    {
        usort($this->buckets, $closure);
    }

    /**
     * @return list<string>
     */
    public function getKeys(): array
    {
        $keys = [];
        foreach ($this->buckets as $bucket) {
            $keys[] = $bucket->getKey();
        }

        return array_values(array_filter($keys));
    }

    public function has(?string $key): bool
    {
        $exists = $this->get($key);

        return $exists !== null;
    }

    public function get(?string $key): ?Bucket
    {
        foreach ($this->buckets as $bucket) {
            if ($bucket->getKey() === $key) {
                return $bucket;
            }
        }

        return null;
    }
}
