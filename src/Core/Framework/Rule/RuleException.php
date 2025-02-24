<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Exception\ScriptExecutionFailedException;
use Shopware\Core\Framework\Script\ScriptException;
use Symfony\Component\HttpFoundation\Response;

#[Package('fundamentals@after-sales')]
class RuleException extends HttpException
{
    public const FRAMEWORK_RULE_OPERATOR_NOT_SUPPORTED = 'FRAMEWORK__RULE_OPERATOR_NOT_SUPPORTED';
    public const FRAMEWORK_RULE_VALUE_NOT_SUPPORTED = 'FRAMEWORK__RULE_VALUE_NOT_SUPPORTED';

    public static function scriptExecutionFailed(string $hook, string $scriptName, \Throwable $previous): ScriptException
    {
        // use own exception class so it can be catched properly
        return new ScriptExecutionFailedException($hook, $scriptName, $previous);
    }

    public static function unsupportedOperator(string $operator, string $class): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::FRAMEWORK_RULE_OPERATOR_NOT_SUPPORTED,
            'Unsupported operator {{ operator }} in {{ class }}',
            ['operator' => $operator, 'class' => $class]
        );
    }

    public static function unsupportedValue(string $type, string $class): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::FRAMEWORK_RULE_VALUE_NOT_SUPPORTED,
            'Unsupported value of type {{ type }} in {{ class }}',
            ['type' => $type, 'class' => $class]
        );
    }
}
