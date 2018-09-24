<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write\Command;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Write\EntityExistence;

class InsertCommand implements WriteCommandInterface
{
    /**
     * @var array
     */
    private $payload;

    /**
     * @var string|EntityDefinition
     */
    private $definition;

    /**
     * @var array
     */
    private $primaryKey;
    /**
     * @var EntityExistence
     */
    private $existence;

    public function __construct(string $definition, array $payload, array $primaryKey, EntityExistence $existence)
    {
        $this->payload = $payload;
        $this->definition = $definition;
        $this->primaryKey = $primaryKey;
        $this->existence = $existence;
    }

    public function isValid(): bool
    {
        return (bool) \count($this->payload);
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getDefinition(): string
    {
        return $this->definition;
    }

    public function getPrimaryKey(): array
    {
        return $this->primaryKey;
    }

    /**
     * @return EntityExistence
     */
    public function getEntityExistence(): EntityExistence
    {
        return $this->existence;
    }
}
