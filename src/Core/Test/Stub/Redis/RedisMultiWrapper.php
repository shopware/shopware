<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Redis;

class RedisMultiWrapper extends \Redis
{
    use RedisMultiCompatibility;

    /**
     * @param array<mixed> $results
     */
    public function __construct(private readonly \Redis $redis, private array $results = [])
    {
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

    /**
     * @param array<mixed> $arguments
     *
     * @return RedisMultiWrapper
     */
    private function doCall(string $name, array $arguments)
    {
        // @phpstan-ignore-next-line
        $this->results[] = $this->redis->$name(...$arguments);

        return $this;
    }
}
