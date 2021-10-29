<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Script\Executor;

class ScriptExecutionFailedException extends \RuntimeException
{
    public function __construct(string $hook, string $scriptName, \Throwable $previous)
    {
        parent::__construct(sprintf(
            'Execution of script "%s" for Hook "%s" failed with message: %s',
            $scriptName,
            $hook,
            $previous->getMessage()
        ), 0, $previous);
    }
}
