<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

/**
 * Contains the result of the entity write process
 */
class EntityWriteResult
{
    public const OPERATION_INSERT = 'insert';
    public const OPERATION_UPDATE = 'update';
    public const OPERATION_DELETE = 'delete';

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
     * @var string
     */
    private $operation;

    /**
     * @param array|string $primaryKey
     */
    public function __construct($primaryKey, array $payload, string $entityName, string $operation, ?EntityExistence $existence = null)
    {
        $this->primaryKey = $primaryKey;
        $this->payload = $payload;
        $this->existence = $existence;

        $this->entityName = $entityName;
        $this->operation = mb_strtolower($operation);

        if (!in_array($this->operation, [self::OPERATION_DELETE, self::OPERATION_INSERT, self::OPERATION_UPDATE], true)) {
            throw new \RuntimeException(sprintf('Unexpected write result operation %s', $operation));
        }
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

    public function getOperation(): string
    {
        return $this->operation;
    }
}
