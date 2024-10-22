<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;
use Twig\Node\Expression\AbstractExpression;

#[Package('checkout')]
class AdapterException extends HttpException
{
    public const UNEXPECTED_TWIG_EXPRESSION = 'FRAMEWORK__UNEXPECTED_TWIG_EXPRESSION';
    public const MISSING_EXTENDING_TWIG_TEMPLATE = 'FRAMEWORK__MISSING_EXTENDING_TWIG_TEMPLATE';
    public const TEMPLATE_SCOPE_DEFINITION_ERROR = 'FRAMEWORK__TEMPLATE_SCOPE_DEFINITION_ERROR';
    public const MISSING_DEPENDENCY_ERROR_CODE = 'FRAMEWORK__FILESYSTEM_ADAPTER_DEPENDENCY_MISSING';
    public const INVALID_TEMPLATE_SYNTAX = 'FRAMEWORK__INVALID_TEMPLATE_SYNTAX';
    public const REDIS_UNKNOWN_CONNECTION = 'FRAMEWORK__REDIS_UNKNOWN_CONNECTION';
    public const REDIS_INVALID_DSN = 'FRAMEWORK__REDIS_INVALID_DSN';
    /**
     * @deprecated tag:v6.7.0 - REDIS_MISSING_CONNECTION_PARAMETER will be removed with no replacement
     *
     * @internal
     */
    public const REDIS_MISSING_CONNECTION_PARAMETER = 'FRAMEWORK__REDIS_MISSING_CONNECTION_PARAMETER';

    public static function unexpectedTwigExpression(AbstractExpression $expression): self
    {
        return new self(
            Response::HTTP_NOT_ACCEPTABLE,
            self::UNEXPECTED_TWIG_EXPRESSION,
            'Unexpected Expression of type "{{ type }}".',
            [
                'type' => $expression::class,
            ]
        );
    }

    public static function missingExtendsTemplate(string $template): self
    {
        return new self(
            Response::HTTP_NOT_ACCEPTABLE,
            self::MISSING_EXTENDING_TWIG_TEMPLATE,
            'Template "{{ template }}" does not have an extending template.',
            [
                'template' => $template,
            ],
        );
    }

    public static function invalidTemplateScope(mixed $scope): self
    {
        return new self(
            Response::HTTP_NOT_ACCEPTABLE,
            self::TEMPLATE_SCOPE_DEFINITION_ERROR,
            'Template scope is wronly defined: {{ scope }}',
            [
                'scope' => $scope,
            ],
        );
    }

    public static function missingDependency(string $dependency): self
    {
        return new self(
            Response::HTTP_FAILED_DEPENDENCY,
            self::MISSING_DEPENDENCY_ERROR_CODE,
            'Missing dependency "{{ dependency }}". Check the suggested composer dependencies for version and install the package.',
            [
                'dependency' => $dependency,
            ],
        );
    }

    public static function invalidTemplateSyntax(string $message): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_TEMPLATE_SYNTAX,
            'Failed rendering Twig string template due syntax error: "{{ message }}"',
            ['message' => $message]
        );
    }

    public static function unknownRedisConnection(string $connectionName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::REDIS_UNKNOWN_CONNECTION,
            'Can\'t provide connection "{{ connectionName }}", check if it\'s configured under framework.redis.connections.',
            [
                'connectionName' => $connectionName,
            ],
        );
    }

    public static function invalidRedisConnectionDsn(string $connectionName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::REDIS_UNKNOWN_CONNECTION,
            'shopware.redis.connections dsn of "%s" connection must be a string.',
            [
                'connectionName' => $connectionName,
            ],
        );
    }

    /**
     * @internal
     *
     * @deprecated tag:v6.7.0 reason:factory-for-deprecation - Will be removed with no replacement as using method getOrCreateFromDsn will be removed
     */
    public static function missingRedisConnectionParameter(?string $connectionName, ?string $dsn): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::REDIS_MISSING_CONNECTION_PARAMETER,
            'Missing required $connectionName or $dsn parameters ({{ connectionName }}, {{ dsn }} provided).',
            [
                'connectionName' => json_encode($connectionName),
                'dsn' => json_encode($dsn),
            ],
        );
    }
}
