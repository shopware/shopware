<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

/**
 * @final
 */
class SetNullOnDeleteCommand extends UpdateCommand
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string> $primaryKey
     */
    public function __construct(
        EntityDefinition $definition,
        array $payload,
        array $primaryKey,
        EntityExistence $existence,
        string $path,
        private bool $enforcedByConstraint
    ) {
        parent::__construct($definition, $payload, $primaryKey, $existence, $path);
    }

    public function isValid(): bool
    {
        // prevent execution if the constraint is enforced on DB level
        return !$this->enforcedByConstraint;
    }

    public function getPrivilege(): ?string
    {
        return null;
    }
}
