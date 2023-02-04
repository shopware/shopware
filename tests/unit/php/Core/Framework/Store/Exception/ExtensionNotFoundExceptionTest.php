<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Exception\ExtensionNotFoundException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package merchant-services
 *
 * @internal
 *
 * @covers \Shopware\Core\Framework\Store\Exception\ExtensionNotFoundException
 */
class ExtensionNotFoundExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        static::assertSame(
            'FRAMEWORK__EXTENSION_NOT_FOUND',
            (new ExtensionNotFoundException('Cannot find extension'))->getErrorCode()
        );
    }

    public function testGetStatusCode(): void
    {
        static::assertSame(
            Response::HTTP_NOT_FOUND,
            (new ExtensionNotFoundException('Cannot find extension'))->getStatusCode()
        );
    }

    public function testGetMessageFromTechnicalName(): void
    {
        static::assertSame(
            'Could not find extension with technical name "SwagPaypal".',
            ExtensionNotFoundException::fromTechnicalName('SwagPaypal')->getMessage()
        );
    }

    public function testGetMessageFromId(): void
    {
        static::assertSame(
            'Could not find extension with id "bda4cdc0a56e43a1973d9f81139e5fcc".',
            ExtensionNotFoundException::fromId('bda4cdc0a56e43a1973d9f81139e5fcc')->getMessage()
        );
    }
}
