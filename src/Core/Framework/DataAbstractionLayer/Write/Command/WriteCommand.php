<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

abstract class WriteCommand
{
    /**
     * @var array
     */
    protected $payload;

    /**
     * @var EntityDefinition
     */
    protected $definition;

    /**
     * @var array
     */
    protected $primaryKey;

    /**
     * @var EntityExistence
     */
    protected $existence;

    /**
     * @var string
     */
    protected $path;

    public function __construct(EntityDefinition $definition, array $payload, array $primaryKey, EntityExistence $existence, string $path)
    {
        $this->payload = $payload;
        $this->definition = $definition;
        $this->primaryKey = $primaryKey;
        $this->existence = $existence;
        $this->path = $path;
    }

    abstract public function getPrivilege(): ?string;

    public function isValid(): bool
    {
        return (bool) \count($this->payload);
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getDefinition(): EntityDefinition
    {
        return $this->definition;
    }

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
        return array_key_exists($storageName, $this->getPayload());
    }
}
