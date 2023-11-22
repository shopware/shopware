<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(ImportExportException::class)]
class ImportExportExceptionTest extends TestCase
{
    public function testItThrowsException(): void
    {
        $testCases = [
            [
                'exception' => ImportExportException::invalidFileAccessToken(),
                'statusCode' => Response::HTTP_BAD_REQUEST,
                'errorCode' => 'CONTENT__IMPORT_EXPORT_FILE_INVALID_ACCESS_TOKEN',
                'message' => 'Access to file denied due to invalid access token',
            ],
            [
                'exception' => ImportExportException::fileNotFound('notFoundFile'),
                'statusCode' => Response::HTTP_NOT_FOUND,
                'errorCode' => 'CONTENT__IMPORT_EXPORT_FILE_NOT_FOUND',
                'message' => 'Cannot find import/export file with id notFoundFile',
            ],
            [
                'exception' => ImportExportException::processingError('Cannot merge file'),
                'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'errorCode' => 'CONTENT__IMPORT_EXPORT_PROCESSING_EXCEPTION',
                'message' => 'Cannot merge file',
            ],
            [
                'exception' => ImportExportException::requiredByUser('foo'),
                'statusCode' => Response::HTTP_BAD_REQUEST,
                'errorCode' => 'CONTENT__IMPORT_EXPORT_REQUIRED_BY_USER',
                'message' => 'foo is set to required by the user but has no value',
            ],
            [
                'exception' => ImportExportException::invalidIdentifier('foo'),
                'statusCode' => Response::HTTP_BAD_REQUEST,
                'errorCode' => 'CONTENT__IMPORT_EXPORT_INVALID_IDENTIFIER',
                'message' => 'The identifier of foo should not contain pipe character.',
            ],
            [
                'exception' => ImportExportException::decorationPattern('foo'),
                'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'errorCode' => '500',
                'message' => 'The getDecorated() function of core class foo cannot be used. This class is the base class.',
            ],
            [
                'exception' => ImportExportException::profileNotFound('null'),
                'statusCode' => Response::HTTP_NOT_FOUND,
                'errorCode' => 'CONTENT__IMPORT_EXPORT_PROFILE_NOT_FOUND',
                'message' => 'Cannot find import/export profile with id null',
            ],
        ];

        foreach ($testCases as $testCase) {
            $this->runTestCase(
                $testCase['exception'],
                $testCase['statusCode'],
                $testCase['errorCode'],
                $testCase['message']
            );
        }
    }

    private function runTestCase(ShopwareHttpException|ImportExportException $exception, int $statusCode, string $errorCode, string $message): void
    {
        static::assertEquals($statusCode, $exception->getStatusCode());
        static::assertEquals($errorCode, $exception->getErrorCode());
        static::assertEquals($message, $exception->getMessage());
    }
}
