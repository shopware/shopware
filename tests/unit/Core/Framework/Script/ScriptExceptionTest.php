<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Script;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Exception\ScriptExecutionFailedException;
use Shopware\Core\Framework\Script\ScriptException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ScriptException::class)]
class ScriptExceptionTest extends TestCase
{
    public function testExecutionFailedException(): void
    {
        $exception = ScriptException::scriptExecutionFailed('hook', 'script', new \RuntimeException('test'));

        static::assertInstanceOf(ScriptExecutionFailedException::class, $exception);
        static::assertSame('FRAMEWORK_SCRIPT_EXECUTION_FAILED', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testHookMethodOutsideOfSalesChannelContextException(): void
    {
        $exception = ScriptException::hookMethodOutsideOfSalesChannelContext('method');

        static::assertSame('FRAMEWORK__HOOK_METHOD_OUTSIDE_SALES_CHANNEL_CONTEXT', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testStorefrontBundleMissingForHookMethodException(): void
    {
        $exception = ScriptException::storefrontBundleMissingForHookMethod('method');

        static::assertSame('FRAMEWORK__HOOK_METHOD_STOREFRONT_BUNDLE_MISSING', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the major feature flag.
     */
    public function testLegacyContextFactoryThrowsInMajorMode(): void
    {
        static::expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: ' . Feature::deprecatedMethodMessage(ScriptException::class, ScriptException::class . '::hookMethodOutsideOfSalesChannelContext', 'v6.8.0.0')));

        ScriptException::hookMethodOutsideOfSalesChannelContext('render');
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the major feature flag.
     */
    public function testLegacyStorefrontFactoryThrowsInMajorMode(): void
    {
        static::expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: ' . Feature::deprecatedMethodMessage(ScriptException::class, ScriptException::class . '::storefrontBundleMissingForHookMethod', 'v6.8.0.0')));

        ScriptException::storefrontBundleMissingForHookMethod('render');
    }

    public function testAccessFromScriptExecutionContextNotAllowedException(): void
    {
        $exception = ScriptException::accessFromScriptExecutionContextNotAllowed('class', 'method');

        static::assertSame('FRAMEWORK__ACCESS_FROM_SCRIPT_EXECUTION_NOT_ALLOWED', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testFunctionDoesNotExistInInterfaceHookException(): void
    {
        $exception = ScriptException::functionDoesNotExistInInterfaceHook('class', 'function');

        static::assertSame('FRAMEWORK__FUNCTION_DOES_NOT_EXIST_IN_INTERFACE_HOOK', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testNoHookServiceFactoryException(): void
    {
        $exception = ScriptException::noHookServiceFactory('class');

        static::assertSame('FRAMEWORK__NO_HOOK_SERVICE_FACTORY', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testServiceNotAvailableInHookException(): void
    {
        $exception = ScriptException::serviceNotAvailableInHook('class', 'service');

        static::assertSame('FRAMEWORK__SERVICE_NOT_AVAILABLE_IN_HOOK', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testServiceAlreadyExistsException(): void
    {
        $exception = ScriptException::serviceAlreadyExists('service');

        static::assertSame('FRAMEWORK__SCRIPT_SERVICE_ALREADY_EXISTS', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testInterfaceHookExecutionNotAllowedException(): void
    {
        $exception = ScriptException::interfaceHookExecutionNotAllowed('class');

        static::assertSame('FRAMEWORK__INTERFACE_HOOK_EXECUTION_NOT_ALLOWED', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testRequiredFunctionMissingInInterfaceHookException(): void
    {
        $exception = ScriptException::requiredFunctionMissingInInterfaceHook('function', 'script');

        static::assertSame('FRAMEWORK__REQUIRED_FUNCTION_MISSING_IN_INTERFACE_HOOK', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }
}
