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
#[Package('merchant-services')]
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

    /**
     * @DisabledFeatures(features={"v6.6.0.0"})
     */
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

    /**
     * @DisabledFeatures(features={"v6.6.0.0"})
     */
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

    /**
     * @DisabledFeatures(features={"v6.6.0.0"})
     */
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

    /**
     * @DisabledFeatures(features={"v6.6.0.0"})
     */
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
}
