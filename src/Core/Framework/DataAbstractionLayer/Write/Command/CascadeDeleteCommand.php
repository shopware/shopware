<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

/**
 * @final tag:v6.5.0
 */
class CascadeDeleteCommand extends DeleteCommand
{
    public function isValid(): bool
    {
        // prevent execution
        return false;
    }

    public function getPrivilege(): ?string
    {
        return null;
    }
}
