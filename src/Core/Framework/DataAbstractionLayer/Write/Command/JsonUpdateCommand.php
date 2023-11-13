<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('core')]
class JsonUpdateCommand extends UpdateCommand
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string> $primaryKey
     */
    public function __construct(
        EntityDefinition $definition,
        private readonly string $storageName,
        array $payload,
        array $primaryKey,
        EntityExistence $existence,
        string $path
    ) {
        parent::__construct($definition, $payload, $primaryKey, $existence, $path);
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }
}
