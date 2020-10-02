<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
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
    protected $primaryKey;

    /**
     * @var array
     */
    protected $payload;

    /**
     * @var EntityExistence|null
     */
    protected $existence;

    /**
     * @var string
     */
    protected $entityName;

    /**
     * @var string
     */
    protected $operation;

    /**
     * @var ChangeSet|null
     */
    protected $changeSet;

    /**
     * @param array|string $primaryKey
     */
    public function __construct($primaryKey, array $payload, string $entityName, string $operation, ?EntityExistence $existence = null, ?ChangeSet $changeSet = null)
    {
        $this->primaryKey = $primaryKey;
        $this->payload = $payload;
        $this->existence = $existence;

        $this->entityName = $entityName;
        $this->operation = mb_strtolower($operation);

        if (!in_array($this->operation, [self::OPERATION_DELETE, self::OPERATION_INSERT, self::OPERATION_UPDATE], true)) {
            throw new \RuntimeException(sprintf('Unexpected write result operation %s', $operation));
        }
        $this->changeSet = $changeSet;
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

    /**
     * @return mixed|null
     */
    public function getProperty(string $property)
    {
        return $this->payload[$property] ?? null;
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

    public function getChangeSet(): ?ChangeSet
    {
        return $this->changeSet;
    }

    public function hasPayload(string $property): bool
    {
        return array_key_exists($property, $this->getPayload());
    }
}
