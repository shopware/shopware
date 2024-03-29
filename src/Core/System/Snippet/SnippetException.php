<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('system-settings')]
class SnippetException extends HttpException
{
    final public const SNIPPET_INVALID_FILTER_NAME = 'SYSTEM__SNIPPET_INVALID_FILTER_NAME';

    final public const SNIPPET_INVALID_LIMIT_QUERY = 'SYSTEM__SNIPPET_INVALID_LIMIT_QUERY';

    public static function invalidFilterName(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SNIPPET_INVALID_FILTER_NAME,
            'Snippet filter name is invalid.'
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
}
