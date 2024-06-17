<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System;

use PHPUnit\Event\Telemetry\System;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Maintenance\System\SystemException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(System::class)]
class SystemExceptionTest extends TestCase
{
    public function testConsoleApplicationNotFound(): void
    {
        $exception = SystemException::consoleApplicationNotFound();

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('MAINTENANCE__SYMFONY_CONSOLE_APPLICATION_NOT_FOUND', $exception->getErrorCode());
        static::assertSame('Symfony console application not found', $exception->getMessage());
        static::assertSame([], $exception->getParameters());
    }

    public function testInvalidVersionSelectionMode(): void
    {
        $exception = SystemException::invalidVersionSelectionMode('invalid');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('MAINTENANCE__MIGRATION_INVALID_VERSION_SELECTION_MODE', $exception->getErrorCode());
        static::assertSame('Version selection mode needs to be one of these values: "all", "blue-green", "safe", but "invalid" was given.', $exception->getMessage());
        static::assertSame(['validModes' => 'all", "blue-green", "safe', 'mode' => 'invalid'], $exception->getParameters());
    }
}
