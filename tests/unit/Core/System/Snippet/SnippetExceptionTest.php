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
#[Package('services-settings')]
#[CoversClass(SnippetException::class)]
class SnippetExceptionTest extends TestCase
{
    public function testInvalidFilterName(): void
    {
        $exception = SnippetException::invalidFilterName();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(SnippetException::SNIPPET_INVALID_FILTER_NAME, $exception->getErrorCode());
        static::assertEquals('Snippet filter name is invalid.', $exception->getMessage());
    }

    public function testInvalidLimitQuery(): void
    {
        $exception = SnippetException::invalidLimitQuery(0);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(SnippetException::SNIPPET_INVALID_LIMIT_QUERY, $exception->getErrorCode());
        static::assertEquals('Limit must be bigger than 1, 0 given.', $exception->getMessage());
    }
}
