<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Shopware\Core\Framework\Struct\Struct;

class SyncResult extends Struct
{
    /**
     * @var array<array<int, mixed>>
     */
    protected array $data = [];

    /**
     * @var array<array<int, mixed>>
     */
    protected array $deleted = [];

    /**
     * @var array<array<int, mixed>>
     */
    protected array $notFound = [];

    /**
     * @param array<array<int, mixed>> $data
     * @param array<array<int, mixed>> $notFound
     * @param array<array<int, mixed>> $deleted
     */
    public function __construct(array $data, array $notFound = [], array $deleted = [])
    {
        $this->data = $data;
        $this->notFound = $notFound;
        $this->deleted = $deleted;
    }

    /**
     * @return array<array<int, mixed>>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getApiAlias(): string
    {
        return 'api_sync_result';
    }

    /**
     * @return array<array<int, mixed>>
     */
    public function getNotFound(): array
    {
        return $this->notFound;
    }

    /**
     * @return array<array<int, mixed>>
     */
    public function getDeleted(): array
    {
        return $this->deleted;
    }
}
