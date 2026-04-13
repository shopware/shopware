<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Exception\InvalidSortingDirectionException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - reason:remove-exception - Will be removed, use DataAbstractionLayerException::invalidSortingDirection() instead
 */
#[CoversClass(InvalidSortingDirectionException::class)]
class InvalidSortingDirectionExceptionTest extends TestCase
{
    public function testException(): void
    {
        $direction = 'foo';
        $exception = new InvalidSortingDirectionException($direction);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__INVALID_SORT_DIRECTION', $exception->getErrorCode());
        static::assertSame('The given sort direction "' . $direction . '" is invalid.', $exception->getMessage());
        static::assertSame(['direction' => $direction], $exception->getParameters());
    }
}
