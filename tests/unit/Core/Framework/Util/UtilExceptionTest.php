<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Util\UtilException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(UtilException::class)]
class UtilExceptionTest extends TestCase
{
    public function testInvalidJson(): void
    {
        $e = UtilException::invalidJson($p = new \JsonException('invalid'));

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals('UTIL_INVALID_JSON', $e->getErrorCode());
        static::assertEquals('JSON is invalid', $e->getMessage());
        static::assertEquals($p, $e->getPrevious());
    }

    public function testInvalidJsonNotList(): void
    {
        $e = UtilException::invalidJsonNotList();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertEquals('UTIL_INVALID_JSON_NOT_LIST', $e->getErrorCode());
        static::assertEquals('JSON cannot be decoded to a list', $e->getMessage());
    }
}
