<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

/**
 * @internal
 */
abstract class FunctionHook extends Hook
{
    /**
     * Returns the name of the function.
     */
    abstract public function getFunctionName(): string;
}
