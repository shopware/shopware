<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;

class WriteResult extends DeleteResult
{
    /**
     * @var EntityWriteResult[]
     */
    protected array $deleted = [];

    /**
     * @var EntityWriteResult[]
     */
    protected array $notFound = [];

    /**
     * @var EntityWriteResult[]
     */
    protected array $written = [];

    public function __construct(array $deleted, array $notFound = [], array $updated = [])
    {
        parent::__construct($deleted, $notFound, $updated);
        $this->deleted = $deleted;
        $this->notFound = $notFound;
        $this->written = $updated;
    }

    public function getDeleted(): array
    {
        return $this->deleted;
    }

    public function getNotFound(): array
    {
        return $this->notFound;
    }

    public function getWritten(): array
    {
        return $this->written;
    }

    public function getApiAlias(): string
    {
        return 'write_result';
    }
}
