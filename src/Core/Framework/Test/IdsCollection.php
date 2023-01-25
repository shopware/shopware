<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class IdsCollection
{
    /**
     * @var array<string, string>
     */
    protected $ids = [];

    /**
     * @param array<string, string> $ids
     */
    public function __construct(array $ids = [])
    {
        $this->ids = $ids;
    }

    public function create(string $key): string
    {
        if (isset($this->ids[$key])) {
            return $this->ids[$key];
        }

        return $this->ids[$key] = Uuid::randomHex();
    }

    public function get(string $key): string
    {
        return $this->create($key);
    }

    /**
     * @param array<string> $keys
     *
     * @return array{id: string}[]
     */
    public function getIdArray(array $keys, bool $bytes = false): array
    {
        $list = $this->getList($keys);

        $list = $bytes ? Uuid::fromHexToBytesList($list) : $list;

        $list = \array_map(static fn (string $id) => ['id' => $id], $list);

        return \array_values($list);
    }

    public function getBytes(string $key): string
    {
        return Uuid::fromHexToBytes($this->get($key));
    }

    /**
     * @param array<string> $keys
     *
     * @return array<string>
     */
    public function getByteList(array $keys): array
    {
        return Uuid::fromHexToBytesList($this->getList($keys));
    }

    /**
     * @param array<string> $keys
     *
     * @return array<string, string>
     */
    public function getList(array $keys): array
    {
        $ordered = [];
        foreach ($keys as $key) {
            $ordered[$key] = $this->get($key);
        }

        return $ordered;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->ids;
    }

    /**
     * @return array<string, string>
     */
    public function prefixed(string $prefix): array
    {
        $ids = [];
        foreach ($this->ids as $key => $id) {
            if (mb_strpos($key, $prefix) === 0) {
                $ids[$key] = $id;
            }
        }

        return $ids;
    }

    public function set(string $key, string $value): void
    {
        $this->ids[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->ids[$key]);
    }

    public function getKey(string $id): ?string
    {
        foreach ($this->ids as $key => $value) {
            if ($value === $id) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param array<string> $ids
     */
    public function getKeys(array $ids): string
    {
        $keys = [];

        foreach ($ids as $id) {
            $key = $this->getKey($id);
            if ($key) {
                $keys[] = $key;
            } else {
                throw new \RuntimeException('Key not found for id ' . $id);
            }
        }

        return implode(', ', $keys);
    }
}
