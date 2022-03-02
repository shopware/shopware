<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

/**
 * Marker that a function does not need to be implemented by a script
 *
 * @internal
 */
abstract class OptionalFunctionHook extends FunctionHook
{
    public static function willBeRequiredInVersion(): ?string
    {
        return null;
    }
}
