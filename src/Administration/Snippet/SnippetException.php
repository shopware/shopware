<?php declare(strict_types=1);

namespace Shopware\Administration\Snippet;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('administration')]
class SnippetException extends HttpException
{
    final public const SNIPPET_DUPLICATED_FIRST_LEVEL_KEY_EXCEPTION = 'SNIPPET__DUPLICATED_FIRST_LEVEL_KEY';
    final public const SNIPPET_EXTEND_OR_OVERWRITE_CORE_EXCEPTION = 'SNIPPET__EXTEND_OR_OVERWRITE_CORE';
    final public const SNIPPET_DEFAULT_LANGUAGE_NOT_GIVEN_EXCEPTION = 'SNIPPET__DEFAULT_LANGUAGE_NOT_GIVEN';

    /**
     * @param list<string> $duplicatedKeys
     */
    public static function duplicatedFirstLevelKey(array $duplicatedKeys): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::SNIPPET_DUPLICATED_FIRST_LEVEL_KEY_EXCEPTION,
            'The following keys on the first level are duplicated and can not be overwritten: {{ duplicatedKeys }}',
            ['duplicatedKeys' => implode(', ', $duplicatedKeys)]
        );
    }

    /**
     * @param list<string> $keys
     */
    public static function extendOrOverwriteCore(array $keys): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::SNIPPET_EXTEND_OR_OVERWRITE_CORE_EXCEPTION,
            'The following keys extend or overwrite the core snippets which is not allowed: {{ keys }}',
            ['keys' => implode(', ', $keys)]
        );
    }

    public static function defaultLanguageNotGiven(string $defaultLanguage): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SNIPPET_DEFAULT_LANGUAGE_NOT_GIVEN_EXCEPTION,
            'The following snippet file must always be provided when providing snippets: {{ defaultLanguage }}',
            ['defaultLanguage' => $defaultLanguage]
        );
    }
}
