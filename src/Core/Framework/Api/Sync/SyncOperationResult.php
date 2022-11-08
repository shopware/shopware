<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Shopware\Core\Framework\Struct\Struct;

class SyncOperationResult extends Struct
{
    /**
     * @var array<mixed>
     */
    protected $result;

    /**
     * @param array<mixed> $result
     */
    public function __construct(array $result)
    {
        $this->result = $result;
    }

    /**
     * @return array<mixed>
     */
    public function getResult(): array
    {
        return $this->result;
    }

    public function hasError(): bool
    {
        foreach ($this->result as $result) {
            if (\count($result['errors']) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string|int $key
     *
     * @return array<mixed>|null
     */
    public function get($key): ?array
    {
        return $this->result[$key] ?? null;
    }

    /**
     * @param string|int $key
     */
    public function has($key): bool
    {
        return isset($this->result[$key]);
    }

    public function resetEntities(): void
    {
        foreach ($this->result as $index => $_writeResult) {
            $this->result[$index]['entities'] = [];
        }
    }

    public function getApiAlias(): string
    {
        return 'api_sync_operation_result';
    }
}
