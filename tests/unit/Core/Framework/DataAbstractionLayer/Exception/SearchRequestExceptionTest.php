<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SearchRequestException::class)]
class SearchRequestExceptionTest extends TestCase
{
    #[TestDox('tryToThrow is a no-op while no error was added')]
    public function testTryToThrowWithoutErrors(): void
    {
        $exception = new SearchRequestException();

        $exception->tryToThrow();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    #[TestDox('added errors make tryToThrow throw')]
    public function testTryToThrowWithErrors(): void
    {
        $exception = new SearchRequestException();
        $exception->add(new \RuntimeException('broken filter'), '/filter/0');

        $this->expectExceptionObject($exception);
        $exception->tryToThrow();
    }

    #[TestDox('getErrors carries the pointer and resolves codes per exception type')]
    public function testGetErrors(): void
    {
        $exception = new SearchRequestException();
        $exception->add(new \RuntimeException('broken filter', 42), '/filter/0');
        $exception->add(DataAbstractionLayerException::invalidFilterQuery('bad query', '/query'), '/query');

        $errors = iterator_to_array($exception->getErrors(), false);

        static::assertCount(2, $errors);
        static::assertSame('42', $errors[0]['code']);
        static::assertSame('broken filter', $errors[0]['detail']);
        static::assertSame('/filter/0', $errors[0]['source']['pointer']);
        static::assertSame(DataAbstractionLayerException::INVALID_FILTER_QUERY, $errors[1]['code']);
        static::assertSame('/query', $errors[1]['source']['pointer']);
    }
}
