<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SystemConfig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SystemConfig\Exception\InvalidDomainException;
use Shopware\Core\System\SystemConfig\Exception\InvalidKeyException;
use Shopware\Core\System\SystemConfig\Exception\InvalidSettingValueException;
use Shopware\Core\System\SystemConfig\SystemConfigException;
use Shopware\Core\Test\Annotation\DisabledFeatures;

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

    public function testInvalidDomainException(): void
    {
        $exception = SystemConfigException::invalidDomain('domain');

        static::assertSame('Invalid domain \'domain\'', $exception->getMessage());
        static::assertSame('domain', $exception->getParameters()['domain']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testInvalidDomainExceptionDeprecated(): void
    {
        $exception = SystemConfigException::invalidDomain('domain');

        static::assertSame('Invalid domain \'domain\'', $exception->getMessage());
        static::assertSame('domain', $exception->getParameters()['domain']);
        static::assertInstanceOf(InvalidDomainException::class, $exception);
    }

    public function testInvalidKeyException(): void
    {
        $exception = SystemConfigException::invalidKey('key');

        static::assertSame('Invalid key \'key\'', $exception->getMessage());
        static::assertSame('key', $exception->getParameters()['key']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testInvalidKeyExceptionDeprecated(): void
    {
        $exception = SystemConfigException::invalidKey('key');

        static::assertSame('Invalid key \'key\'', $exception->getMessage());
        static::assertSame('key', $exception->getParameters()['key']);
        static::assertInstanceOf(InvalidKeyException::class, $exception);
    }

    public function testInvalidSettingValueException(): void
    {
        $exception = SystemConfigException::invalidSettingValueException('key', 'type', 'value');

        static::assertSame('Invalid setting value for key "key". Expected type "type", got "value".', $exception->getMessage());
        static::assertSame('key', $exception->getParameters()['key']);
        static::assertSame('type', $exception->getParameters()['expectedType']);
        static::assertSame('value', $exception->getParameters()['actualType']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testInvalidSettingValueExceptionDeprecated(): void
    {
        $exception = SystemConfigException::invalidSettingValueException('key', 'type', 'value');

        static::assertSame('Invalid value for \'key\'. Must be of type \'type\'. But is of type \'value\'', $exception->getMessage());
        static::assertSame('key', $exception->getParameters()['key']);
        static::assertSame('type', $exception->getParameters()['neededType']);
        static::assertSame('value', $exception->getParameters()['actualType']);
        static::assertInstanceOf(InvalidSettingValueException::class, $exception);
    }
}
