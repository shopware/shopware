<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Store\Exception\CanNotDownloadPluginManagedByComposerException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package merchant-services
 *
 * @internal
 *
 * @covers \Shopware\Core\Framework\Store\Exception\CanNotDownloadPluginManagedByComposerException
 */
class CanNotDownloadPluginManagedByComposerExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        static::assertSame(
            'FRAMEWORK__STORE_CANNOT_DOWNLOAD_PLUGIN_MANAGED_BY_SHOPWARE',
            (new CanNotDownloadPluginManagedByComposerException('reason'))->getErrorCode()
        );
    }

    public function testGetStatusCode(): void
    {
        static::assertSame(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            (new CanNotDownloadPluginManagedByComposerException('reason'))->getStatusCode()
        );
    }

    public function testGetMessage(): void
    {
        static::assertSame(
            'Can not download plugin. Please contact your system administrator. Error: reason',
            (new CanNotDownloadPluginManagedByComposerException('reason'))->getMessage()
        );
    }
}
