<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Exception\ExtensionInstallException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package merchant-services
 *
 * @internal
 * @covers \Shopware\Core\Framework\Store\Exception\ExtensionInstallException
 */
class ExtensionInstallExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        static::assertSame(
            'FRAMEWORK__EXTENSION_INSTALL_EXCEPTION',
            (new ExtensionInstallException('Cannot find extension'))->getErrorCode()
        );
    }

    public function testGetStatusCode(): void
    {
        static::assertSame(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            (new ExtensionInstallException('Cannot find extension'))->getStatusCode()
        );
    }

    public function testGetMessage(): void
    {
        static::assertSame(
            'Cannot find extension',
            (new ExtensionInstallException('Cannot find extension'))->getMessage()
        );
    }
}
