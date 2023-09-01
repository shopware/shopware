<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Exception\ExtensionInstallException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package merchant-services
 *
 * @internal
 *
 * @covers \Shopware\Core\Framework\Store\Exception\ExtensionInstallException
 */
class ExtensionInstallExceptionTest extends TestCase
{
    /**
     * @DisabledFeatures(features={"v6.6.0.0"})
     */
    public function testGetErrorCode(): void
    {
        static::assertSame(
            'FRAMEWORK__EXTENSION_INSTALL_EXCEPTION',
            (new ExtensionInstallException('Cannot find extension'))->getErrorCode()
        );
    }

    /**
     * @DisabledFeatures(features={"v6.6.0.0"})
     */
    public function testGetStatusCode(): void
    {
        static::assertSame(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            (new ExtensionInstallException('Cannot find extension'))->getStatusCode()
        );
    }

    /**
     * @DisabledFeatures(features={"v6.6.0.0"})
     */
    public function testGetMessage(): void
    {
        static::assertSame(
            'Cannot find extension',
            (new ExtensionInstallException('Cannot find extension'))->getMessage()
        );
    }
}
