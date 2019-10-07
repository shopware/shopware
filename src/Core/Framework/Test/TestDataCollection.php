<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class TestDataCollection
{
    /**
     * @var string[]
     */
    protected $ids = [];

    /**
     * @var Context
     */
    protected $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function create(string $key): string
    {
        if (isset($this->ids[$key])) {
            return $this->ids[$key];
        }

        return $this->ids[$key] = Uuid::randomHex();
    }

    public function get(string $key): ?string
    {
        return $this->ids[$key] ?? null;
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

    public function getContext(): Context
    {
        return $this->context;
    }
}
