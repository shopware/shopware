<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

/**
 * @package core
 * Contains the result of the entity write process
 */
class EntityWriteResult
{
    public const OPERATION_INSERT = 'insert';
    public const OPERATION_UPDATE = 'update';
    public const OPERATION_DELETE = 'delete';

    /**
     * @param array<string, string>|string $primaryKey
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private array|string $primaryKey,
        private array $payload,
        private string $entityName,
        private string $operation,
        private ?EntityExistence $existence = null,
        private ?ChangeSet $changeSet = null
    ) {
        $this->operation = mb_strtolower($operation);

        if (!\in_array($this->operation, [self::OPERATION_DELETE, self::OPERATION_INSERT, self::OPERATION_UPDATE], true)) {
            throw new \RuntimeException(sprintf('Unexpected write result operation %s', $operation));
        }
    }

    /**
     * @return array<string, string>|string
     */
    public function getPrimaryKey(): array|string
    {
        return $this->primaryKey;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getProperty(string $property): mixed
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
        return \array_key_exists($property, $this->getPayload());
    }
}
