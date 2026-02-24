<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Redis;

class RedisMultiWrapper extends \Redis
{
    public function __construct(private readonly \Redis $redis, private array $results = [])
    {
    }

    public function exec(): array
    {
        $ret = $this->results;
        $this->results = [];

        return $ret;
    }

    public function del(array|string $key, string ...$other_keys): \Redis|int|false
    {
        $this->results[] = $this->redis->del($key, ...$other_keys);

        return $this;
    }

    public function sMembers(string $key): RedisMultiWrapper
    {
        $this->results[] = $this->redis->sMembers($key);

        return $this;
    }
}
