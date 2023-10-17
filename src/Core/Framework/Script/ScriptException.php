<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Exception\ScriptExecutionFailedException;

#[Package('core')]
class ScriptException extends HttpException
{
    public static function scriptExecutionFailed(string $hook, string $scriptName, \Throwable $previous): self
    {
        // use own exception class so it can be catched properly
        return new ScriptExecutionFailedException($hook, $scriptName, $previous);
    }
}