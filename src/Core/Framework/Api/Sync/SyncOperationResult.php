<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Shopware\Core\Framework\Struct\Struct;

class SyncOperationResult extends Struct
{
    /**
     * @var array
     */
    protected $result;

    public function __construct(array $result)
    {
        $this->result = $result;
    }

    public function getResult(): array
    {
        return $this->result;
    }

    public function hasError(): bool
    {
        foreach ($this->result as $result) {
            if (count($result['errors']) > 0) {
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

    public function resetEntities(): void
    {
        foreach ($this->result as $index => $_writeResult) {
            $this->result[$index]['entities'] = [];
        }
    }
}
