<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

/**
 * Contains the result of the entity write process
 */
class EntityWriteResult
{
    /**
     * @var array|string
     */
    private $primaryKey;

    /**
     * @var array
     */
    private $payload;

    /**
     * @var EntityExistence|null
     */
    private $existence;

    /**
     * @var string
     */
    private $entityName;

    /**
     * @param array|string $primaryKey
     */
    public function __construct($primaryKey, array $payload, string $entityName, ?EntityExistence $existence)
    {
        $this->primaryKey = $primaryKey;
        $this->payload = $payload;
        $this->existence = $existence;

        $this->entityName = $entityName;
    }

    /**
     * @return array|string
     */
    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getExistence(): ?EntityExistence
    {
        return $this->existence;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }
}
