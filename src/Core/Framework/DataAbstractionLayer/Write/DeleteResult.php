<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;

/**
 * @deprecated tag:v6.5.0 - Use \Shopware\Core\Framework\DataAbstractionLayer\Write\WriteResult instead
 */
class DeleteResult
{
    /**
     * @var EntityWriteResult[]
     */
    private $deleted;

    /**
     * @var EntityWriteResult[]
     */
    private $notFound;

    /**
     * @var EntityWriteResult[]
     */
    private $updated = [];

    public function __construct(array $deleted, array $notFound = [], array $updated = [])
    {
        $this->deleted = $deleted;
        $this->notFound = $notFound;
        $this->updated = $updated;
    }

    public function getDeleted(): array
    {
        return $this->deleted;
    }

    public function getNotFound(): array
    {
        return $this->notFound;
    }

    public function addUpdated(array $updated): void
    {
        $this->updated = array_merge_recursive($this->updated, $updated);
    }

    public function getUpdated(): array
    {
        return $this->updated;
    }
}
