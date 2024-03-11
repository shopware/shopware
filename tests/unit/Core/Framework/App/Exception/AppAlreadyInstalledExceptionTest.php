<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\AppAlreadyInstalledException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(AppAlreadyInstalledException::class)]
class AppAlreadyInstalledExceptionTest extends TestCase
{
    public function testException(): void
    {
        $appName = 'test_app';
        $exception = AppException::alreadyInstalled($appName);

        static::assertInstanceOf(AppAlreadyInstalledException::class, $exception);

        static::assertSame(
            'App "test_app" is already installed',
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
            Response::HTTP_CONFLICT,
            $exception->getStatusCode()
        );
    }
}
