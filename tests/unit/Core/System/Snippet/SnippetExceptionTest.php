<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\SnippetException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SnippetException::class)]
class SnippetExceptionTest extends TestCase
{
    public function testInvalidFilterName(): void
    {
        $exception = SnippetException::invalidFilterName();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SnippetException::SNIPPET_INVALID_FILTER_NAME, $exception->getErrorCode());
        static::assertSame('Snippet filter name is invalid.', $exception->getMessage());
    }

    public function testInvalidLimitQuery(): void
    {
        $exception = SnippetException::invalidLimitQuery(0);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SnippetException::SNIPPET_INVALID_LIMIT_QUERY, $exception->getErrorCode());
        static::assertSame('Limit must be bigger than 1, 0 given.', $exception->getMessage());
    }

    public function testInvalidSnippetFile(): void
    {
        $previousException = new \Exception('Previous exception message');
        $exception = SnippetException::invalidSnippetFile('en-GB.json', $previousException);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SnippetException::INVALID_SNIPPET_FILE, $exception->getErrorCode());
        static::assertSame('The snippet file "en-GB.json" is invalid: Previous exception message', $exception->getMessage());
    }

    public function testSnippetFileNotRegistered(): void
    {
        $exception = SnippetException::snippetFileNotRegistered('en-GB');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SnippetException::SNIPPET_FILE_NOT_REGISTERED, $exception->getErrorCode());
        static::assertSame('The base snippet file for locale en-GB is not registered.', $exception->getMessage());
    }

    public function testSnippetSetNotFound(): void
    {
        $exception = SnippetException::snippetSetNotFound('non-existent-set-id');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SnippetException::SNIPPET_SET_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Snippet set with ID "non-existent-set-id" not found.', $exception->getMessage());
    }

    public function testNoArgumentsProvided(): void
    {
        $exception = SnippetException::noArgumentsProvided();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SnippetException::SNIPPET_NO_ARGUMENTS_PROVIDED, $exception->getErrorCode());
        static::assertSame('You must specify either --all or --locales to run the InstallTranslationCommand.', $exception->getMessage());
    }

    public function testNoLocalesArgumentProvided(): void
    {
        $exception = SnippetException::noLocalesArgumentProvided();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SnippetException::SNIPPET_NO_LOCALES_ARGUMENT_PROVIDED, $exception->getErrorCode());
        static::assertSame('The --locales argument must not be empty.', $exception->getMessage());
    }

    public function testInvalidLocalesProvided(): void
    {
        $locales = 'foo-bar';
        $all = 'de-DE,en-GB';

        $exception = SnippetException::invalidLocalesProvided($locales, $all);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SnippetException::SNIPPET_INVALID_LOCALES_PROVIDED, $exception->getErrorCode());
        static::assertSame('Invalid locale codes: "foo-bar". Available codes: "de-DE,en-GB"', $exception->getMessage());
    }

    public function testTranslationConfigurationDirectoryDoesNotExist(): void
    {
        $exception = SnippetException::translationConfigurationDirectoryDoesNotExist('/path/to/nonexistent/directory');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SnippetException::SNIPPET_TRANSLATION_CONFIGURATION_DIRECTORY_DOES_NOT_EXIST, $exception->getErrorCode());
        static::assertSame('Translation configuration directory does not exist: "/path/to/nonexistent/directory".', $exception->getMessage());
    }

    public function testTranslationConfigurationFileDoesNotExist(): void
    {
        $exception = SnippetException::translationConfigurationFileDoesNotExist('file.json');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SnippetException::SNIPPET_TRANSLATION_CONFIGURATION_FILE_DOES_NOT_EXIST, $exception->getErrorCode());
        static::assertSame('Translation configuration file does not exist: "file.json".', $exception->getMessage());
    }

    public function testTranslationConfigurationFileDoesNotExistWithPrevious(): void
    {
        $exception = SnippetException::translationConfigurationFileDoesNotExist('file.json', new \Exception('Previous exception message'));

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SnippetException::SNIPPET_TRANSLATION_CONFIGURATION_FILE_DOES_NOT_EXIST, $exception->getErrorCode());
        static::assertSame('Translation configuration file does not exist: "file.json".', $exception->getMessage());
        static::assertInstanceOf(\Exception::class, $exception->getPrevious());
        static::assertSame('Previous exception message', $exception->getPrevious()->getMessage());
    }

    public function testTranslationConfigurationFileIsEmpty(): void
    {
        $exception = SnippetException::translationConfigurationFileIsEmpty('file.json');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SnippetException::SNIPPET_TRANSLATION_CONFIGURATION_FILE_IS_EMPTY, $exception->getErrorCode());
        static::assertSame('Translation configuration file exists, but is empty: "file.json".', $exception->getMessage());
    }

    public function testLocaleDoesNotExist(): void
    {
        $locale = 'non-existent-locale';
        $exception = SnippetException::localeDoesNotExist($locale);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SnippetException::SNIPPET_CONFIGURED_LOCALE_DOES_NOT_EXIST, $exception->getErrorCode());
        static::assertSame('The configured locale "non-existent-locale" does not exist.', $exception->getMessage());
    }

    public function testLanguageDoesNotExist(): void
    {
        $language = 'non-existent-language';
        $exception = SnippetException::languageDoesNotExist($language);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SnippetException::SNIPPET_CONFIGURED_LANGUAGE_DOES_NOT_EXIST, $exception->getErrorCode());
        static::assertSame('The configured language "non-existent-language" does not exist.', $exception->getMessage());
    }

    public function testInvalidRepositoryUrl(): void
    {
        $exception = SnippetException::invalidRepositoryUrl('http://localhost:8000', new \Exception('Invalid URL'));

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SnippetException::SNIPPET_TRANSLATION_CONFIGURATION_INVALID_REPOSITORY_URL, $exception->getErrorCode());
        static::assertSame('The repository URL "http://localhost:8000" is invalid: Invalid URL', $exception->getMessage());
    }
}
