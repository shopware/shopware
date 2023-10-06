<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Shopware\Core\Framework\Log\Package;

/**
 * Marker that a function does not need to be implemented by a script
 *
 * @internal only rely on the concrete implementations
 */
#[Package('core')]
abstract class OptionalFunctionHook extends FunctionHook
{
    public static function willBeRequiredInVersion(): ?string
    {
        return null;
    }
}
