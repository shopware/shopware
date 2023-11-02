<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Framework\Adapter\Storage;

use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
class ArrayKeyValueStorage extends AbstractKeyValueStorage implements ResetInterface
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private array $config = [])
    {
    }

    public function has(string $key): bool
    {
        return \array_key_exists($key, $this->config);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->config[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($this->config[$key]);
    }

    public function reset(): void
    {
        $this->config = [];
    }
}
