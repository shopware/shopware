<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store;

use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\StoreException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(StoreException::class)]
class StoreExceptionTest extends TestCase
{
    public function testCannotDeleteManaged(): void
    {
        $exception = StoreException::cannotDeleteManaged('test-extension');

        static::assertSame(
            'Extension test-extension is managed by Composer and cannot be deleted',
            $exception->getMessage()
        );

        static::assertSame('FRAMEWORK__STORE_CANNOT_DELETE_COMPOSER_MANAGED', $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testExtensionThemeStillInUse(): void
    {
        $exception = StoreException::extensionThemeStillInUse('abcdefg');

        static::assertSame(
            'The extension with id "abcdefg" can not be removed because its theme is still assigned to a sales channel.',
            $exception->getMessage()
        );

        static::assertSame('FRAMEWORK__EXTENSION_THEME_STILL_IN_USE', $exception->getErrorCode());
        static::assertSame(Response::HTTP_FORBIDDEN, $exception->getStatusCode());
    }

    public function testCouldNotUploadExtensionCorrectly(): void
    {
        $exception = StoreException::couldNotUploadExtensionCorrectly();

        static::assertSame(
            'Extension could not be uploaded correctly.',
            $exception->getMessage()
        );

        static::assertSame('FRAMEWORK__EXTENSION_CANNOT_BE_UPLOADED_CORRECTLY', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testInvalidContextSource(): void
    {
        $exception = StoreException::invalidContextSource('context1', 'context2');

        static::assertSame(
            'Expected context source to be "context1" but got "context2".',
            $exception->getMessage()
        );

        static::assertSame('FRAMEWORK__STORE_DATA_INVALID_CONTEXT_SOURCE', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testMissingIntegrationInContextSource(): void
    {
        $exception = StoreException::missingIntegrationInContextSource('context');

        static::assertSame(
            'No integration available in context source "context"',
            $exception->getMessage()
        );

        static::assertSame('FRAMEWORK__STORE_MISSING_INTEGRATION_IN_CONTEXT_SOURCE', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testStoreError(): void
    {
        $exception = StoreException::storeError(new ClientException('some test message', new Request('GET', 'https://example.com'), new \GuzzleHttp\Psr7\Response()));

        static::assertSame(
            'some test message',
            $exception->getMessage()
        );

        static::assertSame('FRAMEWORK__STORE_ERROR', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testExtensionInstallException(): void
    {
        $message = 'Failed to install extension due to missing dependencies';
        $exception = StoreException::extensionInstallException($message);

        static::assertSame($message, $exception->getMessage());
        static::assertSame('FRAMEWORK__EXTENSION_INSTALL_EXCEPTION', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    public function testExtensionUpdateRequiresConsentAffirmationException(): void
    {
        $appName = 'TestApp';
        $deltas = ['permissions' => ['read' => true]];
        $exception = StoreException::extensionUpdateRequiresConsentAffirmationException($appName, $deltas);

        static::assertSame(
            'Updating app "TestApp" requires a renewed consent affirmation.',
            $exception->getMessage()
        );
        static::assertSame('FRAMEWORK__EXTENSION_UPDATE_REQUIRES_CONSENT_AFFIRMATION', $exception->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(['appName' => $appName, 'deltas' => $deltas], $exception->getParameters());
    }

    public function testExtensionNotFoundFromId(): void
    {
        $id = '123456';
        $exception = StoreException::extensionNotFoundFromId($id);

        static::assertSame(
            'Could not find extension with id "123456"',
            $exception->getMessage()
        );
        static::assertSame('FRAMEWORK__EXTENSION_NOT_FOUND', $exception->getErrorCode());
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(['entity' => 'extension', 'field' => 'id', 'value' => $id], $exception->getParameters());
    }

    public function testExtensionNotFoundFromTechnicalName(): void
    {
        $technicalName = 'TestExtension';
        $exception = StoreException::extensionNotFoundFromTechnicalName($technicalName);

        static::assertSame(
            'Could not find extension with technical name "TestExtension"',
            $exception->getMessage()
        );
        static::assertSame('FRAMEWORK__EXTENSION_NOT_FOUND', $exception->getErrorCode());
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(['entity' => 'extension', 'field' => 'technical name', 'value' => $technicalName], $exception->getParameters());
    }

    public function testExtensionRuntimeExtensionManagementNotAllowed(): void
    {
        $exception = StoreException::extensionRuntimeExtensionManagementNotAllowed();

        static::assertSame(
            'Runtime extension management is disabled',
            $exception->getMessage()
        );
        static::assertSame('FRAMEWORK__EXTENSION_RUNTIME_EXTENSION_MANAGEMENT_NOT_ALLOWED', $exception->getErrorCode());
        static::assertSame(Response::HTTP_FORBIDDEN, $exception->getStatusCode());
    }

    public function testMissingRequestParameter(): void
    {
        $parameterName = 'testParam';
        $path = '/api/test';
        $exception = StoreException::missingRequestParameter($parameterName, $path);

        static::assertSame(
            'Parameter "testParam" is missing.',
            $exception->getMessage()
        );
        static::assertSame('FRAMEWORK__STORE_MISSING_REQUEST_PARAMETER', $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(['parameterName' => $parameterName, 'path' => $path], $exception->getParameters());
    }

    public function testInvalidType(): void
    {
        $expected = 'string';
        $actual = 'integer';
        $exception = StoreException::invalidType($expected, $actual);

        static::assertSame(
            'Expected collection element of type string got integer',
            $exception->getMessage()
        );
        static::assertSame('FRAMEWORK__STORE_INVALID_TYPE', $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testPluginNotAZipFile(): void
    {
        $mimeType = 'application/json';
        $exception = StoreException::pluginNotAZipFile($mimeType);

        static::assertSame(
            'Extension is not a zip file. Got "application/json"',
            $exception->getMessage()
        );
        static::assertSame('FRAMEWORK__PLUGIN_NOT_A_ZIP_FILE', $exception->getErrorCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(['mimeType' => $mimeType], $exception->getParameters());
    }
}
