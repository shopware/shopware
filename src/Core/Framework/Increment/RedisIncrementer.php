<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Increment;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class RedisIncrementer extends AbstractIncrementer
{
    /**
     * @internal
     *
     * @param \Redis|\RedisArray|\RedisCluster|\Predis\ClientInterface|\Relay\Relay $redis
     */
    public function __construct(private $redis)
    {
    }

    public function increment(string $cluster, string $key): void
    {
        $this->redis->incr($this->getKey($cluster, $key));
    }

    public function decrement(string $cluster, string $key): void
    {
        $value = $this->redis->decr($this->getKey($cluster, $key));

        if ($value < 0) {
            $this->redis->set($this->getKey($cluster, $key), 0);
        }
    }

    public function reset(string $cluster, ?string $key = null): void
    {
        if ($key !== null) {
            $this->redis->del($this->getKey($cluster, $key));

            return;
        }

        $keys = $this->getKeys($cluster);

        foreach ($keys as $key) {
            $this->redis->del($key);
        }
    }

    public function list(string $cluster, int $limit = 5, int $offset = 0): array
    {
        $keys = $this->getKeys($cluster);

        if (empty($keys)) {
            return [];
        }

        $rows = $this->redis->mget($keys);
        \assert(\is_array($rows));

        $result = [];

        arsort($rows, \SORT_NUMERIC);

        if ($limit > -1) {
            $rows = \array_slice($rows, $offset, $limit, true);
        }

        foreach ($rows as $index => $count) {
            $key = $keys[$index];

            $key = str_replace(str_replace('*', '', $this->getKey($cluster)), '', $key);
            \assert(\is_string($key));

            $result[$key] = [
                'key' => $key,
                'cluster' => $cluster,
                'pool' => $this->getPool(),
                'count' => max(0, (int) $count),
            ];
        }

        return $result;
    }

    private function getKey(string $cluster, ?string $key = null): string
    {
        if ($key === null) {
            return sprintf('%s:%s:*', $this->poolName, $cluster);
        }

        return sprintf('%s:%s:%s', $this->poolName, $cluster, $key);
    }

    /**
     * @return string[]
     */
    private function getKeys(string $cluster): array
    {
        $keys = $this->redis->keys($this->getKey($cluster));
        \assert(\is_array($keys));

        if (empty($keys) || !\method_exists($this->redis, 'getOption')) {
            return [];
        }

        $prefix = $this->redis->getOption(\Redis::OPT_PREFIX);
        if (\is_string($prefix)) {
            $prefixLength = \strlen($prefix);
            $keys = \array_map(fn ($key) => \str_starts_with($key, $prefix) ? \substr($key, $prefixLength) : $key, $keys);
        }

        return $keys;
    }
}
