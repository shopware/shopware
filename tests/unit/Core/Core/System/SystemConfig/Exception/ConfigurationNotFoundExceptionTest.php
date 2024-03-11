<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Core\System\SystemConfig\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\Exception\ConfigurationNotFoundException;

/**
 * @internal
 */
#[CoversClass(ConfigurationNotFoundException::class)]
class ConfigurationNotFoundExceptionTest extends TestCase
{
    public function testCreation(): void
    {
        $exception = new ConfigurationNotFoundException('test');

        static::assertEquals('SYSTEM__SCOPE_NOT_FOUND', $exception->getErrorCode());
        static::assertEquals(404, $exception->getStatusCode());
        static::assertEquals('Configuration for scope "test" not found.', $exception->getMessage());
    }
}
