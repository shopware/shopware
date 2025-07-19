<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Exception\AppArchiveValidationFailure;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(AppArchiveValidationFailure::class)]
class AppArchiveValidationFailureTest extends TestCase
{
    public function testAppEmpty(): void
    {
        $e = AppArchiveValidationFailure::appEmpty();

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame(AppArchiveValidationFailure::APP_EMPTY, $e->getErrorCode());
        static::assertSame('App does not contain any files', $e->getMessage());
    }

    public function testNoTopLevelFolder(): void
    {
        $e = AppArchiveValidationFailure::noTopLevelFolder();

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame(AppArchiveValidationFailure::APP_NO_TOP_LEVEL_FOLDER, $e->getErrorCode());
        static::assertSame('App zip does not contain any top level folder', $e->getMessage());
    }

    public function testAppNameMismatch(): void
    {
        $e = AppArchiveValidationFailure::appNameMismatch('AppName', 'WrongAppName');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame(AppArchiveValidationFailure::APP_NAME_MISMATCH, $e->getErrorCode());
        static::assertSame('App name does not match expected. Expected: "AppName". Got: "WrongAppName"', $e->getMessage());
    }

    public function testMissingManifest(): void
    {
        $e = AppArchiveValidationFailure::missingManifest();

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame(AppArchiveValidationFailure::APP_MISSING_MANIFEST, $e->getErrorCode());
        static::assertSame('App archive does not contain a manifest.xml file', $e->getMessage());
    }

    public function testDirectoryTraversal(): void
    {
        $e = AppArchiveValidationFailure::directoryTraversal();

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame(AppArchiveValidationFailure::APP_DIRECTORY_TRAVERSAL, $e->getErrorCode());
        static::assertSame('Directory traversal detected', $e->getMessage());
    }

    public function testInvalidPrefix(): void
    {
        $e = AppArchiveValidationFailure::invalidPrefix('somefile.xml', 'AppName');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame(AppArchiveValidationFailure::APP_INVALID_PREFIX, $e->getErrorCode());
        static::assertSame('Detected invalid file/directory "somefile.xml" in the app zip. Expected the directory: "AppName"', $e->getMessage());
    }
}
