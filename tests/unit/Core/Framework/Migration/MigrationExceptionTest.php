<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Migration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\MigrationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(MigrationException::class)]
class MigrationExceptionTest extends TestCase
{
    public function testInvalidVersionSelectionMode(): void
    {
        $exception = MigrationException::invalidVersionSelectionMode('invalid');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('FRAMEWORK__MIGRATION_INVALID_VERSION_SELECTION_MODE', $exception->getErrorCode());
        static::assertSame('Version selection mode needs to be one of these values: "all", "blue-green", "safe", but "invalid" was given.', $exception->getMessage());
        static::assertSame(['validModes' => 'all", "blue-green", "safe', 'mode' => 'invalid'], $exception->getParameters());
    }
}
