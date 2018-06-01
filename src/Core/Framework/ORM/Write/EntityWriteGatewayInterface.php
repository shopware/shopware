<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Write\Command\WriteCommandInterface;
use Shopware\Core\Framework\ORM\Write\Command\WriteCommandQueue;

interface EntityWriteGatewayInterface
{
    /**
     * Used to validate if the provided primary key already exists in the storage.
     * Also used to verify if the provided entity is a parent or child element.
     *
     * @param string|EntityDefinition $definition
     * @param array                   $primaryKey
     * @param array                   $data
     * @param WriteCommandQueue       $commandQueue
     *
     * @return EntityExistence
     */
    public function getExistence(string $definition, array $primaryKey, array $data, WriteCommandQueue $commandQueue): EntityExistence;

    /**
     * @param WriteCommandInterface[] $commands
     */
    public function execute(array $commands): void;
}
