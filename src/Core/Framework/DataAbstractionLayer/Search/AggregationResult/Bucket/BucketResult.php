<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket;

use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;

class BucketResult extends AggregationResult
{
    /**
     * @var Bucket[]
     */
    protected $buckets;

    public function __construct(string $name, array $buckets)
    {
        parent::__construct($name);
        $this->buckets = $buckets;
    }

    /**
     * @return Bucket[]
     */
    public function getBuckets(): array
    {
        return $this->buckets;
    }

    public function sort(\Closure $closure): void
    {
        usort($this->buckets, $closure);
    }

    public function getKeys(): array
    {
        $keys = [];
        foreach ($this->buckets as $bucket) {
            $keys[] = $bucket->getKey();
        }

        return $keys;
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
