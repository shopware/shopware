<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Exception\ScriptExecutionFailedException;
use Shopware\Core\Framework\Script\ScriptException;

#[Package('services-settings')]
class RuleException extends HttpException
{
    public static function scriptExecutionFailed(string $hook, string $scriptName, \Throwable $previous): ScriptException
    {
        // use own exception class so it can be catched properly
        return new ScriptExecutionFailedException($hook, $scriptName, $previous);
    }
}
