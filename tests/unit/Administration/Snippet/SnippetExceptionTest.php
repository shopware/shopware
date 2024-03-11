<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Snippet\SnippetException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(SnippetException::class)]
class SnippetExceptionTest extends TestCase
{
    #[DataProvider('exceptionDataProvider')]
    public function testItThrowsException(SnippetException $exception, int $statusCode, string $errorCode, string $message): void
    {
        $exceptionWasThrown = false;

        try {
            throw $exception;
        } catch (SnippetException $cmsException) {
            static::assertEquals($statusCode, $cmsException->getStatusCode());
            static::assertEquals($errorCode, $cmsException->getErrorCode());
            static::assertEquals($message, $cmsException->getMessage());

            $exceptionWasThrown = true;
        } finally {
            static::assertTrue($exceptionWasThrown, 'Excepted exception with error code ' . $errorCode . ' to be thrown.');
        }
    }

    /**
     * @return array<string, array{exception: SnippetException, statusCode: int, errorCode: string, message: string}>
     */
    public static function exceptionDataProvider(): iterable
    {
        yield SnippetException::SNIPPET_DUPLICATED_FIRST_LEVEL_KEY_EXCEPTION => [
            'exception' => SnippetException::duplicatedFirstLevelKey(['id1', 'id2', 'id3']),
            'statusCode' => Response::HTTP_CONFLICT,
            'errorCode' => SnippetException::SNIPPET_DUPLICATED_FIRST_LEVEL_KEY_EXCEPTION,
            'message' => 'The following keys on the first level are duplicated and can not be overwritten: id1, id2, id3',
        ];

        yield SnippetException::SNIPPET_EXTEND_OR_OVERWRITE_CORE_EXCEPTION => [
            'exception' => SnippetException::extendOrOverwriteCore(['id1', 'id2', 'id3']),
            'statusCode' => Response::HTTP_CONFLICT,
            'errorCode' => SnippetException::SNIPPET_EXTEND_OR_OVERWRITE_CORE_EXCEPTION,
            'message' => 'The following keys extend or overwrite the core snippets which is not allowed: id1, id2, id3',
        ];

        yield SnippetException::SNIPPET_DEFAULT_LANGUAGE_NOT_GIVEN_EXCEPTION => [
            'exception' => SnippetException::defaultLanguageNotGiven('languageId'),
            'statusCode' => Response::HTTP_BAD_REQUEST,
            'errorCode' => SnippetException::SNIPPET_DEFAULT_LANGUAGE_NOT_GIVEN_EXCEPTION,
            'message' => 'The following snippet file must always be provided when providing snippets: languageId',
        ];
    }
}
