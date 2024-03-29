<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Write\FieldException;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\ExpectedArrayException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ExpectedArrayException::class)]
class ExpectedArrayExceptionTest extends TestCase
{
    public function testException(): void
    {
        $e = new ExpectedArrayException('some/path/0');

        static::assertEquals('Expected data at some/path/0 to be an array.', $e->getMessage());
        static::assertEquals('some/path/0', $e->getParameters()['path']);
        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals('FRAMEWORK__WRITE_MALFORMED_INPUT', $e->getErrorCode());
    }
}
