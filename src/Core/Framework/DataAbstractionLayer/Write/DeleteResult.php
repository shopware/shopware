<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

class DeleteResult
{
    /**
     * @var array
     */
    private $deleted;

    /**
     * @var array
     */
    private $notFound;

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
}
