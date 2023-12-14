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
}
