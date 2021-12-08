<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Executor;

/**
 * @deprecated tag:v6.5.0 will be removed, use \Shopware\Core\Framework\Script\Execution\ScriptExecutor instead
 */
class ScriptExecutor
{
    public function __construct()
    {
    }

    public function execute(string $hook, array $scriptContext): void
    {
    }
}
