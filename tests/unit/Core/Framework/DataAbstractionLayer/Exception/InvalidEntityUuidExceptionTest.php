<?php
declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidEntityUuidException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InvalidEntityUuidException::class)]
class InvalidEntityUuidExceptionTest extends TestCase
{
    public function testExceptionStatusCode(): void
    {
        $exception = new InvalidEntityUuidException('invalid-uuid');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
    }

    public function testExceptionErrorCode(): void
    {
        $exception = new InvalidEntityUuidException('invalid-uuid');

        static::assertSame('FRAMEWORK__INVALID_UUID', $exception->getErrorCode());
    }

    public function testExceptionMessage(): void
    {
        $exception = new InvalidEntityUuidException('invalid-uuid');

        static::assertSame('Value is not a valid UUID: invalid-uuid', $exception->getMessage());
    }

    public function testExceptionPrevious(): void
    {
        $exception = new InvalidEntityUuidException('invalid-uuid');

        static::assertInstanceOf(InvalidUuidException::class, $exception->getPrevious());
    }
}
