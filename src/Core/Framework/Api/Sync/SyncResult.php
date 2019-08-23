<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Shopware\Core\Framework\Struct\Struct;

class SyncResult extends Struct
{
    /**
     * @var bool
     */
    protected $success;

    /**
     * @var SyncOperationResult[]
     */
    protected $data = [];

    public function __construct(array $data, bool $success)
    {
        $this->data = $data;
        $this->success = $success;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function get(string $key): ?SyncOperationResult
    {
        return $this->data[$key] ?? null;
    }

    public function add(string $key, SyncOperationResult $result): void
    {
        $this->data[$key] = $result;
    }

    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }
}
