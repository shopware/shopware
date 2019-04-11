<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

interface WriteCommandInterface
{
    public function isValid(): bool;

    public function getDefinition(): EntityDefinition;

    public function getPrimaryKey(): array;

    public function getEntityExistence(): EntityExistence;
}
