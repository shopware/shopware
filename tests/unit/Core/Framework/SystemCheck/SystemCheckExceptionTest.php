<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\SystemCheck;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\SystemCheckException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemCheckException::class)]
class SystemCheckExceptionTest extends TestCase
{
    public function testUnknownCheck(): void
    {
        $exception = SystemCheckException::unknownCheck('MyCheck');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(SystemCheckException::UNKNOWN_CHECK, $exception->getErrorCode());
        static::assertSame('There is no system check named "MyCheck".', $exception->getMessage());
    }
}
