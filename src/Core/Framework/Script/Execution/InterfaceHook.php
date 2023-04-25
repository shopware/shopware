<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only rely on the concrete implementations
 */
#[Package('core')]
abstract class InterfaceHook extends Hook
{
    /**
     * Returns the hook for a specific function in this interface.
     */
    abstract public function getFunction(string $name): FunctionHook;

    /**
     * Services are defined in the function hooks
     */
    public static function getServiceIds(): array
    {
        return [];
    }
}
