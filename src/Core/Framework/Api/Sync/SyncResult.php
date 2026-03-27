<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('framework')]
class SyncResult extends Struct
{
    /**
     * @param list<array<int, mixed>> $data
     * @param list<array<int, mixed>> $notFound
     * @param list<array<int, mixed>> $deleted
     */
    public function __construct(
        protected array $data,
        protected array $notFound = [],
        protected array $deleted = []
    ) {
    }

    /**
     * @return list<array<int, mixed>>
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
     * @return list<array<int, mixed>>
     */
    public function getNotFound(): array
    {
        return $this->notFound;
    }

    /**
     * @return list<array<int, mixed>>
     */
    public function getDeleted(): array
    {
        return $this->deleted;
    }
}
