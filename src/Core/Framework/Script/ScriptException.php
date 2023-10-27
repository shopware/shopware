<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Exception\ScriptExecutionFailedException;
use Shopware\Core\Framework\Script\Execution\Awareness\HookServiceFactory;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class ScriptException extends HttpException
{
    public const HOOK_METHOD_OUTSIDE_SALES_CHANNEL_CONTEXT = 'FRAMEWORK__HOOK_METHOD_OUTSIDE_SALES_CHANNEL_CONTEXT';
    public const HOOK_METHOD_STOREFRONT_BUNDLE_MISSING = 'FRAMEWORK__HOOK_METHOD_STOREFRONT_BUNDLE_MISSING';
    public const ACCESS_FROM_SCRIPT_EXECUTION_NOT_ALLOWED = 'FRAMEWORK__ACCESS_FROM_SCRIPT_EXECUTION_NOT_ALLOWED';
    public const FUNCTION_DOES_NOT_EXIST_IN_INTERFACE_HOOK = 'FRAMEWORK__FUNCTION_DOES_NOT_EXIST_IN_INTERFACE_HOOK';
    public const NO_HOOK_SERVICE_FACTORY = 'FRAMEWORK__NO_HOOK_SERVICE_FACTORY';
    public const SERVICE_NOT_AVAILABLE_IN_HOOK = 'FRAMEWORK__SERVICE_NOT_AVAILABLE_IN_HOOK';
    public const SERVICE_ALREADY_EXISTS = 'FRAMEWORK__SCRIPT_SERVICE_ALREADY_EXISTS';
    public const INTERFACE_HOOK_EXECUTION_NOT_ALLOWED = 'FRAMEWORK__INTERFACE_HOOK_EXECUTION_NOT_ALLOWED';
    public const REQUIRED_FUNCTION_MISSING_IN_INTERFACE_HOOK = 'FRAMEWORK__REQUIRED_FUNCTION_MISSING_IN_INTERFACE_HOOK';

    public static function scriptExecutionFailed(string $hook, string $scriptName, \Throwable $previous): self
    {
        // use own exception class so it can be catched properly
        return new ScriptExecutionFailedException($hook, $scriptName, $previous);
    }

    public static function hookMethodOutsideOfSalesChannelContext(string $method): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::HOOK_METHOD_OUTSIDE_SALES_CHANNEL_CONTEXT,
            'Method "{{ method }}" can only be called from inside the `SalesChannelContext`.',
            ['method' => $method]
        );
    }

    public static function storefrontBundleMissingForHookMethod(string $method): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::HOOK_METHOD_STOREFRONT_BUNDLE_MISSING,
            'Method "{{ method }}" can only be called if the `storefront`-bundle is installed.',
            ['method' => $method]
        );
    }

    public static function accessFromScriptExecutionContextNotAllowed(string $class, string $method): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::ACCESS_FROM_SCRIPT_EXECUTION_NOT_ALLOWED,
            'Method "{{ method }}" of class "{{ class }}" can not be called from inside a script.',
            ['method' => $method, 'class' => $class]
        );
    }

    public static function functionDoesNotExistInInterfaceHook(string $class, string $function): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::FUNCTION_DOES_NOT_EXIST_IN_INTERFACE_HOOK,
            'Function "{{ function }}" does not exist for InterfaceHook "{{ class }}".',
            ['function' => $function, 'class' => $class]
        );
    }

    public static function noHookServiceFactory(string $class): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::NO_HOOK_SERVICE_FACTORY,
            'Service "{{ class }}" must extend the abstract "{{ base }}" so that this service may also be used in scripts.',
            ['class' => $class, 'base' => HookServiceFactory::class]
        );
    }

    public static function serviceNotAvailableInHook(string $class, string $hook): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SERVICE_NOT_AVAILABLE_IN_HOOK,
            'The service `{{ class }}` is not available in `{{ hook }}`-hook.',
            ['class' => $class, 'hook' => $hook]
        );
    }

    public static function serviceAlreadyExists(string $class): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SERVICE_ALREADY_EXISTS,
            'Service with name "{{ class }}" already exists',
            ['class' => $class]
        );
    }

    public static function interfaceHookExecutionNotAllowed(string $class): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INTERFACE_HOOK_EXECUTION_NOT_ALLOWED,
            'Tried to execute InterfaceHook "{{ class }}", but InterfaceHooks should not be executed, execute the functions of the hook instead',
            ['class' => $class]
        );
    }

    public static function requiredFunctionMissingInInterfaceHook(string $functionName, string $scriptName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::REQUIRED_FUNCTION_MISSING_IN_INTERFACE_HOOK,
            'Required function "{{ functionName }}" missing in script "{{ scriptName }}", please make sure you add the required block in your script.',
            ['functionName' => $functionName, 'scriptName' => $scriptName]
        );
    }
}
