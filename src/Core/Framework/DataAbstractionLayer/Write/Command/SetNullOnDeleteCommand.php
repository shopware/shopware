<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

class SetNullOnDeleteCommand extends UpdateCommand
{
    private bool $enforcedByConstraint;

    public function __construct(EntityDefinition $definition, array $payload, array $primaryKey, EntityExistence $existence, string $path, bool $enforcedByConstraint)
    {
        parent::__construct($definition, $payload, $primaryKey, $existence, $path);
        $this->enforcedByConstraint = $enforcedByConstraint;
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
