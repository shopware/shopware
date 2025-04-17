<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\StoreException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Store\StoreException
 */
#[Package('services-settings')]
class StoreExceptionTest extends TestCase
{
    public function testCannotDeleteManaged(): void
    {
        $exception = StoreException::cannotDeleteManaged('test-extension');

        static::assertEquals(
            'Extension test-extension is managed by Composer and cannot be deleted',
            $exception->getMessage()
        );

        static::assertEquals('FRAMEWORK__STORE_CANNOT_DELETE_COMPOSER_MANAGED', $exception->getErrorCode());
        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testExtensionThemeStillInUse(): void
    {
        $exception = StoreException::extensionThemeStillInUse('abcdefg');

        static::assertEquals(
            'The extension with id "abcdefg" can not be removed because its theme is still assigned to a sales channel.',
            $exception->getMessage()
        );

        static::assertEquals('FRAMEWORK__EXTENSION_THEME_STILL_IN_USE', $exception->getErrorCode());
        static::assertEquals(Response::HTTP_FORBIDDEN, $exception->getStatusCode());
    }

    #[DisabledFeatures(['v6.6.0.0'])]
    public function testExtensionInstallException(): void
    {
        $exception = StoreException::extensionInstallException('Extension not found');

        static::assertEquals(
            'Extension not found',
            $exception->getMessage()
        );

        static::assertEquals('FRAMEWORK__EXTENSION_INSTALL_EXCEPTION', $exception->getErrorCode());
        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
    }

    #[DisabledFeatures(['v6.6.0.0'])]
    public function testExtensionUpdateRequiresConsentAffirmationException(): void
    {
        $exception = StoreException::extensionUpdateRequiresConsentAffirmationException('test-app', [
            'permissions' => [
                'product' => ['read'],
                'categories' => ['read'],
            ],
        ]);

        static::assertEquals(
            'Updating app "test-app" requires a renewed consent affirmation.',
            $exception->getMessage()
        );

        static::assertEquals('FRAMEWORK__EXTENSION_UPDATE_REQUIRES_CONSENT_AFFIRMATION', $exception->getErrorCode());
        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertEquals([
            'appName' => 'test-app',
            'deltas' => [
                'permissions' => [
                    'product' => ['read'],
                    'categories' => ['read'],
                ],
            ],
        ], $exception->getParameters());
    }

    #[DisabledFeatures(['v6.6.0.0'])]
    public function testExtensionNotFoundFromId(): void
    {
        $exception = StoreException::extensionNotFoundFromId('123');

        static::assertEquals(
            'Could not find extension with id "123".',
            $exception->getMessage()
        );

        static::assertEquals('FRAMEWORK__EXTENSION_NOT_FOUND', $exception->getErrorCode());
        static::assertEquals(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
    }

    #[DisabledFeatures(['v6.6.0.0'])]
    public function testExtensionNotFoundFromTechnicalName(): void
    {
        $exception = StoreException::extensionNotFoundFromTechnicalName('test-app');

        static::assertEquals(
            'Could not find extension with technical name "test-app".',
            $exception->getMessage()
        );

        static::assertEquals('FRAMEWORK__EXTENSION_NOT_FOUND', $exception->getErrorCode());
        static::assertEquals(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
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
}
