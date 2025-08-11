<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SnippetException extends HttpException
{
    final public const SNIPPET_INVALID_FILTER_NAME = 'SYSTEM__SNIPPET_INVALID_FILTER_NAME';

    final public const SNIPPET_INVALID_LIMIT_QUERY = 'SYSTEM__SNIPPET_INVALID_LIMIT_QUERY';

    final public const SNIPPET_FILE_NOT_REGISTERED = 'SYSTEM__SNIPPET_FILE_NOT_REGISTERED';

    final public const SNIPPET_FILTER_NOT_FOUND = 'SYSTEM__SNIPPET_FILTER_NOT_FOUND';

    final public const SNIPPET_SET_NOT_FOUND = 'SYSTEM__SNIPPET_SET_NOT_FOUND';

    final public const INVALID_SNIPPET_FILE = 'SYSTEM__INVALID_SNIPPET_FILE';

    final public const JSON_NOT_FOUND = 'SYSTEM__JSON_NOT_FOUND';

    final public const SNIPPET_NO_ARGUMENTS_PROVIDED = 'SYSTEM__NO_ARGUMENTS_PROVIDED';

    final public const SNIPPET_NO_LOCALES_ARGUMENT_PROVIDED = 'SYSTEM__NO_LOCALES_ARGUMENT_PROVIDED';

    final public const SNIPPET_INVALID_LOCALES_PROVIDED = 'SYSTEM__INVALID_LOCALES_PROVIDED';

    final public const SNIPPET_TRANSLATION_CONFIGURATION_DIRECTORY_DOES_NOT_EXIST = 'SYSTEM__TRANSLATION_CONFIGURATION_DIRECTORY_DOES_NOT_EXISTS';

    final public const SNIPPET_TRANSLATION_CONFIGURATION_FILE_DOES_NOT_EXIST = 'SYSTEM__TRANSLATION_CONFIGURATION_FILE_DOES_NOT_EXISTS';

    final public const SNIPPET_TRANSLATION_CONFIGURATION_FILE_IS_EMPTY = 'SYSTEM__TRANSLATION_CONFIGURATION_FILE_DOES_IS_EMPTY';

    final public const SNIPPET_CONFIGURED_LOCALE_DOES_NOT_EXIST = 'SYSTEM__PROVIDED_LOCALE_DOES_NOT_EXIST';

    final public const SNIPPET_CONFIGURED_LANGUAGE_DOES_NOT_EXIST = 'SYSTEM__LANGUAGE_DOES_NOT_EXISTS';

    final public const SNIPPET_TRANSLATION_CONFIGURATION_INVALID_REPOSITORY_URL = 'SYSTEM__SNIPPET_TRANSLATION_CONFIGURATION_INVALID_REPOSITORY_URL';

    public static function invalidFilterName(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SNIPPET_INVALID_FILTER_NAME,
            'Snippet filter name is invalid.'
        );
    }

    public static function filterNotFound(string $filterName, string $class): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SNIPPET_FILTER_NOT_FOUND,
            'The filter "{{ filter }}" was not found in "{{ class }}".',
            ['filter' => $filterName, 'class' => $class]
        );
    }

    public static function invalidLimitQuery(int $limit): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SNIPPET_INVALID_LIMIT_QUERY,
            'Limit must be bigger than 1, {{ limit }} given.',
            ['limit' => $limit]
        );
    }

    public static function invalidSnippetFile(string $filePath, \Throwable $previous): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_SNIPPET_FILE,
            'The snippet file "{{ filePath }}" is invalid: {{ message }}',
            ['filePath' => $filePath, 'message' => $previous->getMessage()],
            $previous
        );
    }

    public static function snippetFileNotRegistered(string $locale): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SNIPPET_FILE_NOT_REGISTERED,
            'The base snippet file for locale {{ locale }} is not registered.',
            ['locale' => $locale]
        );
    }

    public static function snippetSetNotFound(string $snippetSetId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SNIPPET_SET_NOT_FOUND,
            'Snippet set with ID "{{ snippetSetId }}" not found.',
            ['snippetSetId' => $snippetSetId]
        );
    }

    public static function jsonNotFound(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::JSON_NOT_FOUND,
            'Snippet JSON file not found. Please check the path and ensure the file exists.'
        );
    }

    public static function noArgumentsProvided(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SNIPPET_NO_ARGUMENTS_PROVIDED,
            'You must specify either --all or --locales to run the InstallTranslationCommand.'
        );
    }

    public static function noLocalesArgumentProvided(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SNIPPET_NO_LOCALES_ARGUMENT_PROVIDED,
            'The --locales argument must not be empty.'
        );
    }

    public static function invalidLocalesProvided(string $locales, string $all): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SNIPPET_INVALID_LOCALES_PROVIDED,
            'Invalid locale codes: "{{ locales }}". Available codes: "{{ all }}"',
            [
                'locales' => $locales,
                'all' => $all,
            ]
        );
    }

    public static function translationConfigurationDirectoryDoesNotExist(string $path): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SNIPPET_TRANSLATION_CONFIGURATION_DIRECTORY_DOES_NOT_EXIST,
            'Translation configuration directory does not exist: "{{ directory }}".',
            [
                'directory' => $path,
            ]
        );
    }

    public static function translationConfigurationFileDoesNotExist(string $file, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SNIPPET_TRANSLATION_CONFIGURATION_FILE_DOES_NOT_EXIST,
            'Translation configuration file does not exist: "{{ file }}".',
            [
                'file' => $file,
            ],
            $previous
        );
    }

    public static function translationConfigurationFileIsEmpty(string $file): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SNIPPET_TRANSLATION_CONFIGURATION_FILE_IS_EMPTY,
            'Translation configuration file exists, but is empty: "{{ file }}".',
            [
                'file' => $file,
            ]
        );
    }

    public static function localeDoesNotExist(string $locale): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SNIPPET_CONFIGURED_LOCALE_DOES_NOT_EXIST,
            'The configured locale "{{ locale }}" does not exist.',
            [
                'locale' => $locale,
            ]
        );
    }

    public static function languageDoesNotExist(string $language): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SNIPPET_CONFIGURED_LANGUAGE_DOES_NOT_EXIST,
            'The configured language "{{ language }}" does not exist.',
            [
                'language' => $language,
            ]
        );
    }

    public static function invalidRepositoryUrl(string $url, \Throwable $previous): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SNIPPET_TRANSLATION_CONFIGURATION_INVALID_REPOSITORY_URL,
            'The repository URL "{{ url }}" is invalid: {{ message }}',
            [
                'url' => $url,
                'message' => $previous->getMessage(),
            ],
            $previous
        );
    }
}
