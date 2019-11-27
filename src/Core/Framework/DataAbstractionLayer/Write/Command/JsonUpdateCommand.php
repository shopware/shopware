<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

class JsonUpdateCommand extends UpdateCommand
{
    /**
     * @var string
     */
    private $storageName;

    public function __construct(
        EntityDefinition $definition,
        string $storageName,
        array $payload,
        array $primaryKey,
        EntityExistence $existence,
        string $path
    ) {
        parent::__construct($definition, $payload, $primaryKey, $existence, $path);
        $this->storageName = $storageName;
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }
}
