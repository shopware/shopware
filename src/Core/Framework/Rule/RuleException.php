<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Script\Exception\ScriptExecutionFailedException;
use Shopware\Core\Framework\Script\ScriptException;
use Symfony\Component\HttpFoundation\Response;

#[Package('fundamentals@after-sales')]
class RuleException extends HttpException
{
    public const RULE_OPERATOR_NOT_SUPPORTED = 'FRAMEWORK__RULE_OPERATOR_NOT_SUPPORTED';
    public const VALUE_NOT_SUPPORTED = 'CONTENT__RULE_VALUE_NOT_SUPPORTED';
    public const MULTIPLE_NOT_RULES = 'CONTENT__TOO_MANY_NOT_RULES';

    public static function scriptExecutionFailed(string $hook, string $scriptName, \Throwable $previous): ScriptException
    {
        // use own exception class so it can be caught properly
        return new ScriptExecutionFailedException($hook, $scriptName, $previous);
    }

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will return self
     */
    public static function unsupportedOperator(string $operator, string $class): self|UnsupportedOperatorException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new UnsupportedOperatorException($operator, $class);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::RULE_OPERATOR_NOT_SUPPORTED,
            'Unsupported operator {{ operator }} in {{ class }}',
            ['operator' => $operator, 'class' => $class]
        );
    }

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - Will return self
     */
    public static function unsupportedValue(string $type, string $class): self|UnsupportedValueException
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new UnsupportedValueException($type, $class);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::VALUE_NOT_SUPPORTED,
            'Unsupported value of type {{ type }} in {{ class }}',
            ['type' => $type, 'class' => $class]
        );
    }

    public static function onlyOneNotRule(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MULTIPLE_NOT_RULES,
            'NOT rule can only hold one rule'
        );
    }
}
