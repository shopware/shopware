<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Exception\FilterNotFoundException;
use Shopware\Core\System\Snippet\Exception\InvalidSnippetFileException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('system-settings')]
class SnippetException extends HttpException
{
    final public const SNIPPET_INVALID_FILTER_NAME = 'SYSTEM__SNIPPET_INVALID_FILTER_NAME';
    final public const SNIPPET_INVALID_LIMIT_QUERY = 'SYSTEM__SNIPPET_INVALID_LIMIT_QUERY';
    final public const SNIPPET_FILE_NOT_REGISTERED = 'SYSTEM__SNIPPET_FILE_NOT_REGISTERED';
    final public const SNIPPET_FILTER_NOT_FOUND = 'SYSTEM__SNIPPET_FILTER_NOT_FOUND';
    final public const SNIPPET_SET_NOT_FOUND = 'SYSTEM__SNIPPET_SET_NOT_FOUND';
    final public const INVALID_SNIPPET_FILE = 'SYSTEM__INVALID_SNIPPET_FILE';

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
        if (!Feature::isActive('v6.7.0.0')) {
            return new FilterNotFoundException($filterName, $class);
        }

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
        if (!Feature::isActive('v6.7.0.0')) {
            return new InvalidSnippetFileException($locale);
        }

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
}
