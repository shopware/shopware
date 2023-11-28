<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Exception\ExtensionInstallException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Store\Exception\ExtensionInstallException
 */
#[Package('services-settings')]
class ExtensionInstallExceptionTest extends TestCase
{
    #[DisabledFeatures(['v6.6.0.0'])]
    public function testGetErrorCode(): void
    {
        static::assertSame(
            'FRAMEWORK__EXTENSION_INSTALL_EXCEPTION',
            (new ExtensionInstallException('Cannot find extension'))->getErrorCode()
        );
    }

    #[DisabledFeatures(['v6.6.0.0'])]
    public function testGetStatusCode(): void
    {
        static::assertSame(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            (new ExtensionInstallException('Cannot find extension'))->getStatusCode()
        );
    }

    #[DisabledFeatures(['v6.6.0.0'])]
    public function testGetMessage(): void
    {
        static::assertSame(
            'Cannot find extension',
            (new ExtensionInstallException('Cannot find extension'))->getMessage()
        );
    }
}
