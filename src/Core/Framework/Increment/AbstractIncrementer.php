<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Increment;

abstract class AbstractIncrementer
{
    protected string $poolName;

    protected array $config;

    abstract public function getDecorated(): self;

    abstract public function decrement(string $cluster, string $key): void;

    abstract public function increment(string $cluster, string $key): void;

    /**
     * limit -1 means no limit
     */
    abstract public function list(string $cluster, int $limit = 5, int $offset = 0): array;

    abstract public function reset(string $cluster, ?string $key = null): void;

    public function getPool(): string
    {
        return $this->poolName;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @internal
     */
    public function setPool(string $poolName): void
    {
        $this->poolName = $poolName;
    }

    /**
     * @internal
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }
}
