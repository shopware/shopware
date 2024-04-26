<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
abstract class WriteCommand
{
    protected string $entityName;

    protected bool $failed = false;

    /**
     * @var array<string, string>
     */
    protected array $decodedPrimaryKey = [];

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $primaryKey
     */
    public function __construct(
        EntityDefinition $definition,
        protected array $payload,
        protected array $primaryKey,
        protected EntityExistence $existence,
        protected string $path
    ) {
        $this->entityName = $definition->getEntityName();

        $this->setDecodedPrimaryKey($definition);
    }

    abstract public function getPrivilege(): ?string;

    public function isValid(): bool
    {
        return (bool) \count($this->payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @internal
     */
    public function addPayload(string $key, mixed $value): void
    {
        $this->payload[$key] = $value;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    /**
     * @return array<string, string>
     */
    public function getPrimaryKey(): array
    {
        return $this->primaryKey;
    }

    /**
     * @return array<string, string>
     */
    public function getDecodedPrimaryKey(): array
    {
        return $this->decodedPrimaryKey;
    }

    public function getEntityExistence(): EntityExistence
    {
        return $this->existence;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function hasField(string $storageName): bool
    {
        return \array_key_exists($storageName, $this->getPayload());
    }

    public function setFailed(bool $failed): void
    {
        $this->failed = $failed;
    }

    public function isFailed(): bool
    {
        return $this->failed;
    }

    private function setDecodedPrimaryKey(EntityDefinition $definition): void
    {
        $primaryKeys = $definition->getPrimaryKeys()->filter(static fn (Field $field) => !$field instanceof VersionField
            && !$field instanceof ReferenceVersionField
            && $field instanceof StorageAware);

        foreach ($primaryKeys as $primaryKey) {
            if (!$primaryKey instanceof StorageAware) {
                continue;
            }

            if (!isset($this->primaryKey[$primaryKey->getStorageName()])) {
                continue;
            }

            if (!$primaryKey instanceof IdField) {
                $this->decodedPrimaryKey[$primaryKey->getPropertyName()] = $this->primaryKey[$primaryKey->getStorageName()];

                continue;
            }

            $this->decodedPrimaryKey[$primaryKey->getPropertyName()] = Uuid::fromBytesToHex($this->primaryKey[$primaryKey->getStorageName()]);
        }
    }
}
