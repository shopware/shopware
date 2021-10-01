<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class IdsCollection
{
    /**
     * @var Context
     */
    public $context;

    /**
     * @var string[]
     */
    protected $ids = [];

    public function __construct(?Context $context = null)
    {
        if (!$context) {
            $context = Context::createDefaultContext();
        }
        $this->context = $context;
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

    public function getBytes(string $key): string
    {
        return Uuid::fromHexToBytes($this->get($key));
    }

    public function getList(array $keys): array
    {
        $ordered = [];
        foreach ($keys as $key) {
            $ordered[$key] = $this->get($key);
        }

        return $ordered;
    }

    public function all(): array
    {
        return $this->ids;
    }

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

    public function getContext(): Context
    {
        return $this->context;
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
}
