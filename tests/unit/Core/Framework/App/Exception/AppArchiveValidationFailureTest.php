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

        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertEquals(AppArchiveValidationFailure::APP_EMPTY, $e->getErrorCode());
        static::assertEquals('App does not contain any files', $e->getMessage());
    }

    public function testNoTopLevelFolder(): void
    {
        $e = AppArchiveValidationFailure::noTopLevelFolder();

        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertEquals(AppArchiveValidationFailure::APP_NO_TOP_LEVEL_FOLDER, $e->getErrorCode());
        static::assertEquals('App zip does not contain any top level folder', $e->getMessage());
    }

    public function testAppNameMismatch(): void
    {
        $e = AppArchiveValidationFailure::appNameMismatch('AppName', 'WrongAppName');

        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertEquals(AppArchiveValidationFailure::APP_NAME_MISMATCH, $e->getErrorCode());
        static::assertEquals('App name does not match expected. Expected: "AppName". Got: "WrongAppName"', $e->getMessage());
    }

    public function testMissingManifest(): void
    {
        $e = AppArchiveValidationFailure::missingManifest();

        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertEquals(AppArchiveValidationFailure::APP_MISSING_MANIFEST, $e->getErrorCode());
        static::assertEquals('App archive does not contain a manifest.xml file', $e->getMessage());
    }

    public function testDirectoryTraversal(): void
    {
        $e = AppArchiveValidationFailure::directoryTraversal();

        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertEquals(AppArchiveValidationFailure::APP_DIRECTORY_TRAVERSAL, $e->getErrorCode());
        static::assertEquals('Directory traversal detected', $e->getMessage());
    }

    public function testInvalidPrefix(): void
    {
        $e = AppArchiveValidationFailure::invalidPrefix('somefile.xml', 'AppName');

        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertEquals(AppArchiveValidationFailure::APP_INVALID_PREFIX, $e->getErrorCode());
        static::assertEquals('Detected invalid file/directory "somefile.xml" in the app zip. Expected the directory: "AppName"', $e->getMessage());
    }
}
