<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
abstract class WriteCommand
{
    protected bool $failed = false;

    /**
     * @param array<string, mixed> $payload
     * @param array<string> $primaryKey
     */
    public function __construct(
        protected EntityDefinition $definition,
        protected array $payload,
        protected array $primaryKey,
        protected EntityExistence $existence,
        protected string $path
    ) {
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

    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }

    public function getEntityName(): string
    {
        return $this->definition->getEntityName();
    }

    /**
     * @return array<string>
     */
    public function getPrimaryKey(): array
    {
        return $this->primaryKey;
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
}
