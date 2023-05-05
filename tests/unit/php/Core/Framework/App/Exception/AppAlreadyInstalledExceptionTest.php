<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Exception\AppAlreadyInstalledException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\Exception\AppAlreadyInstalledException
 */
class AppAlreadyInstalledExceptionTest extends TestCase
{
    public function testException(): void
    {
        $appName = 'test_app';
        $exception = new AppAlreadyInstalledException($appName);

        static::assertSame(
            'App with name "test_app" is already installed.',
            $exception->getMessage()
        );

        static::assertSame(
            ['appName' => $appName],
            $exception->getParameters()
        );

        static::assertSame(
            'FRAMEWORK__APP_ALREADY_INSTALLED',
            $exception->getErrorCode()
        );

        static::assertSame(
            Response::HTTP_BAD_REQUEST,
            $exception->getStatusCode()
        );
    }
}
