<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Exception\AppDownloadException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(AppDownloadException::class)]
class AppDownloadExceptionTest extends TestCase
{
    public function testCannotWrite(): void
    {
        $e = AppDownloadException::cannotWrite('/tmp/app.zip', 'verboten');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame(AppDownloadException::APP_DOWNLOAD_WRITE_FAILED, $e->getErrorCode());
        static::assertSame('App archive could not be written to "/tmp/app.zip". Error: "verboten"."', $e->getMessage());
    }

    public function testTransportError(): void
    {
        $e = AppDownloadException::transportError('https://app-zip.com');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame(AppDownloadException::APP_DOWNLOAD_TRANSPORT_ERROR, $e->getErrorCode());
        static::assertSame('App could not be downloaded from: "https://app-zip.com".', $e->getMessage());
    }

    public function testTransportErrorWithPreviousException(): void
    {
        $previous = new \RuntimeException('some error');
        $e = AppDownloadException::transportError('https://app-zip.com', $previous);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame(AppDownloadException::APP_DOWNLOAD_TRANSPORT_ERROR, $e->getErrorCode());
        static::assertSame('App could not be downloaded from: "https://app-zip.com".', $e->getMessage());
        static::assertSame($previous, $e->getPrevious());
    }
}
