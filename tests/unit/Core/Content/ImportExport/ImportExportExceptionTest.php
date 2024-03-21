<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(ImportExportException::class)]
class ImportExportExceptionTest extends TestCase
{
    #[DataProvider('exceptionProvider')]
    public function testExceptions(
        \Closure $exceptionFunction,
        bool $deprecated,
        int $statusCode,
        string $errorCode,
        string $message
    ): void {
        $exception = $exceptionFunction();
        if (!Feature::isActive('v6.7.0.0') && $deprecated) {
            static::markTestSkipped();
        }

        \assert($exception instanceof ImportExportException);
        static::assertSame($statusCode, $exception->getStatusCode());
        static::assertSame($errorCode, $exception->getErrorCode());
        static::assertSame($message, $exception->getMessage());
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    public static function exceptionProvider(): iterable
    {
        yield [
            'exceptionFunction' => fn () => ImportExportException::invalidFileAccessToken(),
            'deprecated' => false,
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT_FILE_INVALID_ACCESS_TOKEN',
            'message' => 'Access to file denied due to invalid access token',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::fileNotFound('notFoundFile'),
            'deprecated' => false,
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => 'CONTENT__IMPORT_EXPORT_FILE_NOT_FOUND',
            'message' => 'Cannot find import/export file with id notFoundFile',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::processingError('Cannot merge file'),
            'deprecated' => false,
            'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'errorCode' => 'CONTENT__IMPORT_EXPORT_PROCESSING_EXCEPTION',
            'message' => 'Cannot merge file',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::requiredByUser('foo'),
            'deprecated' => false,
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT_REQUIRED_BY_USER',
            'message' => 'foo is set to required by the user but has no value',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::invalidIdentifier('foo'),
            'deprecated' => false,
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT_INVALID_IDENTIFIER',
            'message' => 'The identifier of foo should not contain pipe character.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::decorationPattern('foo'),
            'deprecated' => false,
            'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'errorCode' => '500',
            'message' => 'The getDecorated() function of core class foo cannot be used. This class is the base class.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::profileNotFound('null'),
            'deprecated' => false,
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => 'CONTENT__IMPORT_EXPORT_PROFILE_NOT_FOUND',
            'message' => 'Cannot find import/export profile with id null',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::couldNotOpenFile('foo'),
            'deprecated' => false,
            'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'errorCode' => 'CONTENT__IMPORT_EXPORT__COULD_NOT_OPEN_FILE',
            'message' => 'Could not open file at: foo',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::couldNotCreateFile('foo'),
            'deprecated' => false,
            'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'errorCode' => 'CONTENT__IMPORT_EXPORT__COULD_NOT_CREATE_FILE',
            'message' => 'Could not create file in directory: foo',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::couldNotCopyFile('foo'),
            'deprecated' => false,
            'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'errorCode' => 'CONTENT__IMPORT_EXPORT__COULD_NOT_COPY_FILE',
            'message' => 'Could not copy file from buffer to "foo"',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::couldNotWriteToBuffer(),
            'deprecated' => false,
            'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'errorCode' => 'CONTENT__IMPORT_EXPORT__COULD_NOT_WRITE_TO_BUFFER',
            'message' => 'Could not write to buffer',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::fieldCannotBeExported('foo'),
            'deprecated' => false,
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'IMPORT_EXPORT__FIELD_CANNOT_BE_EXPORTED',
            'message' => 'Field of type foo cannot be exported.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::fileEmpty('foo'),
            'deprecated' => true,
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT_FILE_EMPTY',
            'message' => 'The file foo is empty.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::fileNotReadable('foo'),
            'deprecated' => true,
            'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'errorCode' => 'CONTENT__IMPORT_FILE_IS_NOT_READABLE',
            'message' => 'Import file is not readable at foo.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::filePathNotFound(),
            'deprecated' => false,
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT__FILE_PATH_NOT_FOUND',
            'message' => 'File path does not exist.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::invalidFileContent('foo'),
            'deprecated' => true,
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT_INVALID_FILE_CONTENT',
            'message' => 'The content of the file foo is invalid.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::logEntityNotFound('bar'),
            'deprecated' => false,
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT__LOG_ENTITY_NOT_FOUND',
            'message' => 'Import/Export log "bar" not found.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::profileWithoutMappings('bar'),
            'deprecated' => false,
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT_PROFILE_WITHOUT_MAPPINGS',
            'message' => 'Import/Export profile "bar" has no mappings.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::profileWrongType('bar', 'foo'),
            'deprecated' => true,
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => 'CONTENT__IMPORT_EXPORT_PROFILE_WRONG_TYPE',
            'message' => 'The import/export profile with id bar can only be used for foo',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::unexpectedFileType('foo', 'bar'),
            'deprecated' => true,
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_FILE_HAS_UNEXPECTED_TYPE',
            'message' => 'Given file does not match MIME-Type for selected profile. Given: foo. Expected: bar',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::unknownActivity('foo'),
            'deprecated' => false,
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT__UNKNOWN_ACTIVITY',
            'message' => 'The activity "foo" could not be processed.',
        ];
    }
}
