<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Increment;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @deprecated tag:v6.6.0 - reason:becomes-internal - Type hint to AbstractIncrementer, implementations are internal and should not be used for type hints
 */
#[Package('core')]
class RedisIncrementer extends AbstractIncrementer
{
    public function __construct(private readonly \Redis $redis)
    {
    }

    public function getDecorated(): AbstractIncrementer
    {
        throw new DecorationPatternException(self::class);
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

        $keys = $this->redis->keys($this->getKey($cluster));

        foreach ($keys as $key) {
            $this->redis->del($key);
        }
    }

    public function list(string $cluster, int $limit = 5, int $offset = 0): array
    {
        $keys = $this->redis->keys($this->getKey($cluster));

        if (empty($keys)) {
            return [];
        }

        $rows = $this->redis->mget($keys);

        $result = [];

        arsort($rows, \SORT_NUMERIC);

        if ($limit > -1) {
            $rows = \array_slice($rows, $offset, $limit, true);
        }

        foreach ($rows as $index => $count) {
            $key = $keys[$index];

            $key = str_replace(str_replace('*', '', $this->getKey($cluster)), '', $key);

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
}
