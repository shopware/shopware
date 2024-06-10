<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SystemConfig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\SystemConfigException;

/**
 * @internal
 */
#[CoversClass(SystemConfigException::class)]
class SystemConfigExceptionTest extends TestCase
{
    public function testSystemConfigKeyIsManagedBySystems(): void
    {
        $exception = SystemConfigException::systemConfigKeyIsManagedBySystems('configKey');

        static::assertSame('The system configuration key "configKey" cannot be changed, as it is managed by the Shopware yaml file configuration system provided by Symfony.', $exception->getMessage());
        static::assertSame('configKey', $exception->getParameters()['configKey']);
    }
}
