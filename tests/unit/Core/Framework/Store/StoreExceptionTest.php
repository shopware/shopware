<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\StoreException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('services-settings')]
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
}
