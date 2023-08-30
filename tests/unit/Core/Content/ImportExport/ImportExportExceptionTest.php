<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\ImportExport\ImportExportException
 */
#[Package('system-settings')]
class ImportExportExceptionTest extends TestCase
{
    /**
     * @dataProvider exceptionDataProvider
     */
    public function testItThrowsException(ShopwareHttpException|ImportExportException $exception, int $statusCode, string $errorCode, string $message): void
    {
        try {
            throw $exception;
        } catch (ShopwareHttpException|ImportExportException $importExportException) {
            $caughtException = $importExportException;
        }

        static::assertEquals($statusCode, $caughtException->getStatusCode());
        static::assertEquals($errorCode, $caughtException->getErrorCode());
        static::assertEquals($message, $caughtException->getMessage());
    }

    /**
     * @return array<string, array{exception: ImportExportException|ShopwareHttpException, statusCode: int, errorCode: string, message: string}>
     */
    public static function exceptionDataProvider(): iterable
    {
        yield 'CONTENT__IMPORT_EXPORT_FILE_INVALID_ACCESS_TOKEN' => [
            'exception' => ImportExportException::invalidFileAccessToken(),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT_FILE_INVALID_ACCESS_TOKEN',
            'message' => 'Access to file denied due to invalid access token',
        ];

        yield 'CONTENT__IMPORT_EXPORT_FILE_NOT_FOUND' => [
            'exception' => ImportExportException::fileNotFound('notFoundFile'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => 'CONTENT__IMPORT_EXPORT_FILE_NOT_FOUND',
            'message' => 'Cannot find import/export file with id notFoundFile',
        ];
    }
}
