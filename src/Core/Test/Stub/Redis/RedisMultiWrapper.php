<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Redis;

class RedisMultiWrapper
{
    /**
     * @param array<mixed> $results
     */
    public function __construct(private readonly \Redis $redis, private array $results = [])
    {
    }

    /**
     * @param array<mixed> $arguments
     *
     * @return RedisMultiWrapper
     */
    public function __call(string $name, array $arguments)
    {
        // @phpstan-ignore-next-line
        $this->results[] = $this->redis->$name(...$arguments);

        return $this;
    }

    /**
     * @return mixed[]
     */
    public function exec(): array
    {
        $ret = $this->results;
        $this->results = [];

        return $ret;
    }
}
