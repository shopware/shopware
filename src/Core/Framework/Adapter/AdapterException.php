<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter;

use Shopware\Core\Framework\Adapter\Filesystem\Exception\AdapterFactoryNotFoundException;
use Shopware\Core\Framework\Adapter\Filesystem\Exception\DuplicateFilesystemFactoryException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Symfony\Component\Asset\Exception\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;
use Twig\Node\Expression\AbstractExpression;
use Twig\Source;

#[Package('checkout')]
class AdapterException extends HttpException
{
    public const UNEXPECTED_TWIG_EXPRESSION = 'FRAMEWORK__UNEXPECTED_TWIG_EXPRESSION';
    public const MISSING_EXTENDING_TWIG_TEMPLATE = 'FRAMEWORK__MISSING_EXTENDING_TWIG_TEMPLATE';
    public const TEMPLATE_SCOPE_DEFINITION_ERROR = 'FRAMEWORK__TEMPLATE_SCOPE_DEFINITION_ERROR';
    public const TEMPLATE_SW_USE_SYNTAX_ERROR = 'FRAMEWORK__TEMPLATE_SW_USE_SYNTAX_ERROR';
    public const MISSING_DEPENDENCY_ERROR_CODE = 'FRAMEWORK__FILESYSTEM_ADAPTER_DEPENDENCY_MISSING';
    public const INVALID_TEMPLATE_SYNTAX = 'FRAMEWORK__INVALID_TEMPLATE_SYNTAX';
    public const REDIS_UNKNOWN_CONNECTION = 'FRAMEWORK__REDIS_UNKNOWN_CONNECTION';
    public const INVALID_ASSET_URL = 'FRAMEWORK__INVALID_ASSET_URL';
    final public const INVALID_ARGUMENT = 'FRAMEWORK__INVALID_ARGUMENT_EXCEPTION';
    final public const STRING_TEMPLATE_RENDERING_FAILED = 'FRAMEWORK__STRING_TEMPLATE_RENDERING_FAILED';
    final public const CURRENCY_FILTER_ERROR = 'FRAMEWORK__CURRENCY_FILTER_ERROR';
    final public const SECURITY_FUNCTION_NOT_ALLOWED = 'FRAMEWORK__SECURITY_FUNCTION_NOT_ALLOWED';
    final public const CACHE_COMPRESSION_ERROR = 'FRAMEWORK__CACHE_COMPRESSION_ERROR';
    final public const PCRE_FUNCTION_ERROR = 'FRAMEWORK__PCRE_FUNCTION_ERROR';
    final public const CACHE_DIRECTORY_ERROR = 'FRAMEWORK__CACHE_DIRECTORY_ERROR';
    final public const CURRENCY_FILTER_MISSING_CONTEXT = 'FRAMEWORK__CURRENCY_FILTER_MISSING_CONTEXT';
    final public const CURRENCY_FILTER_MISSING_ISO_CODE = 'FRAMEWORK__CURRENCY_FILTER_MISSING_ISO_CODE';
    final public const FILESYSTEM_FACTORY_NOT_FOUND = 'FRAMEWORK__FILESYSTEM_FACTORY_NOT_FOUND';
    final public const DUPLICATE_FILESYSTEM_FACTORY = 'FRAMEWORK__DUPLICATE_FILESYSTEM_FACTORY';
    final public const OPERATOR_NOT_SUPPORTED = 'FRAMEWORK__OPERATOR_NOT_SUPPORTED';

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
            self::OPERATOR_NOT_SUPPORTED,
            'Unsupported operator {{ operator }} in {{ class }}',
            ['operator' => $operator, 'class' => $class]
        );
    }

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

    public static function swUseSyntaxError(int $line, Source $context): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::TEMPLATE_SW_USE_SYNTAX_ERROR,
            'The template references in a "sw_use" statement must be a string.',
            ['line' => $line, 'context' => $context]
        );
    }

    public static function renderingTemplateFailed(string $message): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::STRING_TEMPLATE_RENDERING_FAILED,
            'Failed rendering string template using Twig: {{ message }}',
            ['message' => $message]
        );
    }

    public static function unknownRedisConnection(string $connectionName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::REDIS_UNKNOWN_CONNECTION,
            'Can\'t provide connection "{{ connectionName }}", check if it\'s configured under shopware.redis.connections.',
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

    public static function invalidAssetUrl(InvalidArgumentException $previous): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_ASSET_URL,
            'Invalid asset URL. Check the "APP_URL" environment variable. Error message: {{ message }}',
            [
                'message' => $previous->getMessage(),
            ],
            $previous
        );
    }

    public static function invalidArgument(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_ARGUMENT,
            $message
        );
    }

    public static function currencyFilterError(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CURRENCY_FILTER_ERROR,
            $message
        );
    }

    public static function securityFunctionNotAllowed(string $function): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::SECURITY_FUNCTION_NOT_ALLOWED,
            'Function "{{ function }}" is not allowed',
            ['function' => $function]
        );
    }

    public static function cacheCompressionError(string $message): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CACHE_COMPRESSION_ERROR,
            'Error while processing cache compression. {{ message }}',
            ['message' => $message],
        );
    }

    public static function pcreFunctionError(string $function, string $error): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PCRE_FUNCTION_ERROR,
            'Error while processing Twig {{ function }} function. Error: {{ error }}',
            ['function' => $function, 'error' => $error]
        );
    }

    public static function cacheDirectoryError(string $directory): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CACHE_DIRECTORY_ERROR,
            'Unable to write in the "{{ directory }}" directory',
            ['directory' => $directory]
        );
    }

    public static function currencyFilterMissingContext(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CURRENCY_FILTER_MISSING_CONTEXT,
            'Error while processing Twig currency filter. No context or locale given.'
        );
    }

    public static function currencyFilterMissingIsoCode(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CURRENCY_FILTER_MISSING_ISO_CODE,
            'Error while processing Twig currency filter. Could not resolve currencyIsoCode.'
        );
    }

    /**
     * @deprecated tag:v6.8.0 - It will return a self instance instead of AdapterFactoryNotFoundException - reason:remove-exception
     */
    public static function filesystemFactoryNotFound(string $type): AdapterFactoryNotFoundException|self
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new AdapterFactoryNotFoundException($type);
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::FILESYSTEM_FACTORY_NOT_FOUND,
            'Filesystem factory for type "{{ type }}" not found.',
            ['type' => $type]
        );
    }

    /**
     * @deprecated tag:v6.8.0 - It will return a self instance instead of DuplicateFilesystemFactoryException - reason:remove-exception
     */
    public static function duplicateFilesystemFactory(string $type): DuplicateFilesystemFactoryException|self
    {
        if (!Feature::isActive('v6.8.0.0')) {
            return new DuplicateFilesystemFactoryException($type);
        }

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::DUPLICATE_FILESYSTEM_FACTORY,
            'Filesystem factory for type "{{ type }}" already exists.',
            ['type' => $type]
        );
    }
}
