<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Command;

class SetNullOnDeleteCommand extends UpdateCommand
{
    public function isValid(): bool
    {
        // prevent execution
        return false;
    }
}
