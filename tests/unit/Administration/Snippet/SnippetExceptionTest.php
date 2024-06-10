<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Snippet\SnippetException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(SnippetException::class)]
class SnippetExceptionTest extends TestCase
{
    public function testDuplicatedFirstLevelKey(): void
    {
        $exception = SnippetException::duplicatedFirstLevelKey(['id1', 'id2', 'id3']);

        static::assertSame(Response::HTTP_CONFLICT, $exception->getStatusCode());
        static::assertSame(SnippetException::SNIPPET_DUPLICATED_FIRST_LEVEL_KEY_EXCEPTION, $exception->getErrorCode());
        static::assertSame('The following keys on the first level are duplicated and can not be overwritten: id1, id2, id3', $exception->getMessage());
        static::assertSame(['duplicatedKeys' => 'id1, id2, id3'], $exception->getParameters());
    }

    public function testDefaultLanguageNotGiven(): void
    {
        $exception = SnippetException::defaultLanguageNotGiven('languageId');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SnippetException::SNIPPET_DEFAULT_LANGUAGE_NOT_GIVEN_EXCEPTION, $exception->getErrorCode());
        static::assertSame('The following snippet file must always be provided when providing snippets: languageId', $exception->getMessage());
        static::assertSame(['defaultLanguage' => 'languageId'], $exception->getParameters());
    }

    public function testExtendOrOverwriteCore(): void
    {
        $exception = SnippetException::extendOrOverwriteCore(['id1', 'id2', 'id3']);

        static::assertSame(Response::HTTP_CONFLICT, $exception->getStatusCode());
        static::assertSame(SnippetException::SNIPPET_EXTEND_OR_OVERWRITE_CORE_EXCEPTION, $exception->getErrorCode());
        static::assertSame('The following keys extend or overwrite the core snippets which is not allowed: id1, id2, id3', $exception->getMessage());
        static::assertSame(['keys' => 'id1, id2, id3'], $exception->getParameters());
    }
}
