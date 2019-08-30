<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Shopware\Core\Framework\Struct\Struct;

class SyncOperationResult extends Struct
{
    /**
     * @var string
     */
    protected $key;

    /**
     * @var bool
     */
    protected $success;

    /**
     * @var array
     */
    protected $result;

    public function __construct(string $key, array $result, bool $success)
    {
        $this->key = $key;
        $this->result = $result;
        $this->success = $success;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getResult(): ?array
    {
        return $this->result;
    }

    public function hasError(): bool
    {
        foreach ($this->result as $result) {
            if ($result['error']) {
                return true;
            }
        }

        return false;
    }

    public function get($key): ?array
    {
        return $this->result[$key] ?? null;
    }

    public function has($key): bool
    {
        return isset($this->result[$key]);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }
}
