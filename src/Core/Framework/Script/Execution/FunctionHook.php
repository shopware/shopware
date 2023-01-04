<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
abstract class FunctionHook extends Hook
{
    /**
     * Returns the name of the function.
     */
    abstract public function getFunctionName(): string;
}
