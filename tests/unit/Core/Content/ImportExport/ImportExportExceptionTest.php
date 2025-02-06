<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ImportExportException::class)]
class ImportExportExceptionTest extends TestCase
{
    #[DataProvider('exceptionProvider')]
    public function testExceptions(
        \Closure $exceptionFunction,
        int $statusCode,
        string $errorCode,
        string $message
    ): void {
        $exception = $exceptionFunction();

        static::assertInstanceOf(ShopwareHttpException::class, $exception);
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
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT_FILE_INVALID_ACCESS_TOKEN',
            'message' => 'Access to file denied due to invalid access token',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::fileNotFound('notFoundFile'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => 'CONTENT__IMPORT_EXPORT_FILE_NOT_FOUND',
            'message' => 'Cannot find import/export file with id notFoundFile',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::processingError('Cannot merge file'),
            'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'errorCode' => 'CONTENT__IMPORT_EXPORT_PROCESSING_EXCEPTION',
            'message' => 'Cannot merge file',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::requiredByUser('foo'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT_REQUIRED_BY_USER',
            'message' => 'foo is set to required by the user but has no value',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::invalidIdentifier('foo'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT_INVALID_IDENTIFIER',
            'message' => 'The identifier of foo should not contain pipe character.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::decorationPattern('foo'),
            'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'errorCode' => '500',
            'message' => 'The getDecorated() function of core class foo cannot be used. This class is the base class.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::profileNotFound('null'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => 'CONTENT__IMPORT_EXPORT_PROFILE_NOT_FOUND',
            'message' => 'Cannot find import/export profile with id null',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::couldNotOpenFile('foo'),
            'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'errorCode' => 'CONTENT__IMPORT_EXPORT__COULD_NOT_OPEN_FILE',
            'message' => 'Could not open file at: foo',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::couldNotCreateFile('foo'),
            'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'errorCode' => 'CONTENT__IMPORT_EXPORT__COULD_NOT_CREATE_FILE',
            'message' => 'Could not create file in directory: foo',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::couldNotCopyFile('foo'),
            'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'errorCode' => 'CONTENT__IMPORT_EXPORT__COULD_NOT_COPY_FILE',
            'message' => 'Could not copy file from buffer to "foo"',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::couldNotWriteToBuffer(),
            'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'errorCode' => 'CONTENT__IMPORT_EXPORT__COULD_NOT_WRITE_TO_BUFFER',
            'message' => 'Could not write to buffer',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::fieldCannotBeExported('foo'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'IMPORT_EXPORT__FIELD_CANNOT_BE_EXPORTED',
            'message' => 'Field of type foo cannot be exported.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::fileEmpty('foo'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT_FILE_EMPTY',
            'message' => 'The file foo is empty.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::fileNotReadable('foo'),
            'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'errorCode' => 'CONTENT__IMPORT_FILE_IS_NOT_READABLE',
            'message' => 'Import file is not readable at foo.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::filePathNotFound(),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT__FILE_PATH_NOT_FOUND',
            'message' => 'File path does not exist.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::invalidFileContent('foo'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT_INVALID_FILE_CONTENT',
            'message' => 'The content of the file foo is invalid.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::logEntityNotFound('bar'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT__LOG_ENTITY_NOT_FOUND',
            'message' => 'Import/Export log "bar" not found.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::profileWithoutMappings('bar'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT_PROFILE_WITHOUT_MAPPINGS',
            'message' => 'Import/Export profile "bar" has no mappings.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::profileWrongType('bar', 'foo'),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => 'CONTENT__IMPORT_EXPORT_PROFILE_WRONG_TYPE',
            'message' => 'The import/export profile with id bar can only be used for foo',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::unexpectedFileType('foo', 'bar'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_FILE_HAS_UNEXPECTED_TYPE',
            'message' => 'Given file does not match MIME-Type for selected profile. Given: foo. Expected: bar',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::unknownActivity('foo'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT__UNKNOWN_ACTIVITY',
            'message' => 'The activity "foo" could not be processed.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::invalidRequestParameter('foo'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT__INVALID_REQUEST_PARAMETER',
            'message' => 'The parameter "foo" is invalid.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::missingPrivilege(['foo', 'bar']),
            'statusCode' => Response::HTTP_FORBIDDEN,
            'errorCode' => 'CONTENT__IMPORT_EXPORT__MISSING_PRIVILEGE',
            'message' => 'Missing privilege: ["foo","bar"]',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::profileSearchEmpty(),
            'statusCode' => Response::HTTP_NOT_FOUND,
            'errorCode' => 'CONTENT__IMPORT_EXPORT__PROFILE_SEARCH_EMPTY',
            'message' => 'The search for profiles returned no results.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::importCommandFailed('Some message that explains the error.'),
            'statusCode' => Response::HTTP_INTERNAL_SERVER_ERROR,
            'errorCode' => 'CONTENT__IMPORT_EXPORT__COMMAND_FAILED',
            'message' => 'Some message that explains the error.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::duplicateTechnicalName('foo'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT__DUPLICATE_TECHNICAL_NAME',
            'message' => 'The technical name "foo" is not unique.',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::deserializationFailed('id', 'foo', 'bar'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT__DESERIALIZE_FAILED',
            'message' => 'Deserialization failed for field "id" with value "foo" to type "bar"',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::deserializationFailed('id', null, 'bar'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT__DESERIALIZE_FAILED',
            'message' => 'Deserialization failed for field "id" with value "" to type "bar"',
        ];

        yield [
            'exceptionFunction' => fn () => ImportExportException::invalidInstanceType('foo', 'bar'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => 'CONTENT__IMPORT_EXPORT__INVALID_INSTANCE_TYPE',
            'message' => 'Expected "foo" to be an instance of "bar".',
        ];
    }
}
