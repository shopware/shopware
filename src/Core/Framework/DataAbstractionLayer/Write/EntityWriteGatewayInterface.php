<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandQueue;

interface EntityWriteGatewayInterface
{
    /**
     * Used to validate if the provided primary key already exists in the storage.
     * Also used to verify if the provided entity is a parent or child element.
     */
    public function getExistence(EntityDefinition $definition, array $primaryKey, array $data, WriteCommandQueue $commandQueue): EntityExistence;

    /**
     * @param WriteCommand[] $commands
     */
    public function execute(array $commands, WriteContext $context): void;
}
