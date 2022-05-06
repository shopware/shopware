<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-final - Will be @final
 * @final
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
