<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;

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

    public function __construct(array $deleted, array $notFound = [])
    {
        $this->deleted = $deleted;
        $this->notFound = $notFound;
    }

    public function getDeleted(): array
    {
        return $this->deleted;
    }

    public function getNotFound(): array
    {
        return $this->notFound;
    }

    public function setUpdated(array $updated): void
    {
        $this->updated = $updated;
    }

    public function getUpdated(): array
    {
        return $this->updated;
    }
}
