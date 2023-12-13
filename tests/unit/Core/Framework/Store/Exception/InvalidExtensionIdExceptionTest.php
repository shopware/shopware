<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Exception\InvalidExtensionIdException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(InvalidExtensionIdException::class)]
class InvalidExtensionIdExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        static::assertSame(
            'FRAMEWORK__INVALID_EXTENSION_ID',
            (new InvalidExtensionIdException())->getErrorCode()
        );
    }

    public function testGetStatusCode(): void
    {
        static::assertSame(
            Response::HTTP_BAD_REQUEST,
            (new InvalidExtensionIdException())->getStatusCode()
        );
    }

    public function testGetMessage(): void
    {
        static::assertSame(
            'The extension id must be an non empty numeric value.',
            (new InvalidExtensionIdException())->getMessage()
        );
    }
}
