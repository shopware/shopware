<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Write\Command;

use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Write\EntityExistence;

interface WriteCommandInterface
{
    public function isValid(): bool;

    /**
     * @return string|EntityDefinition
     */
    public function getDefinition(): string;

    public function getPrimaryKey(): array;

    public function getEntityExistence(): EntityExistence;
}
