<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('core')]
class WriteResult
{
    /**
     * @param array<string, list<EntityWriteResult>> $deleted
     * @param array<string, list<EntityWriteResult>> $notFound
     * @param array<string, list<EntityWriteResult>> $written
     */
    public function __construct(
        protected array $deleted,
        protected array $notFound = [],
        protected array $written = []
    ) {
    }

    /**
     * @return array<string, list<EntityWriteResult>>
     */
    public function getDeleted(): array
    {
        return $this->deleted;
    }

    /**
     * @return array<string, list<EntityWriteResult>>
     */
    public function getNotFound(): array
    {
        return $this->notFound;
    }

    /**
     * @return array<string, list<EntityWriteResult>>
     */
    public function getWritten(): array
    {
        return $this->written;
    }

    public function getApiAlias(): string
    {
        return 'write_result';
    }
}
