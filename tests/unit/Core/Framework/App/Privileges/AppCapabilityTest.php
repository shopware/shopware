<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Privileges;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Xml\Tax\Tax;
use Shopware\Core\Framework\App\Privileges\AppCapability;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppCapability::class)]
class AppCapabilityTest extends TestCase
{
    public function testCanReturnsTrueWhenActionGranted(): void
    {
        $appId = Uuid::randomHex();

        $privileges = static::createStub(Privileges::class);
        $privileges->method('getPrivileges')->willReturn([$appId => ['customer:read', Tax::PERMISSION]]);

        static::assertTrue((new AppCapability($privileges))->can($appId, Tax::PERMISSION));
    }

    public function testCanReturnsFalseWhenActionNotGranted(): void
    {
        $appId = Uuid::randomHex();

        $privileges = static::createStub(Privileges::class);
        $privileges->method('getPrivileges')->willReturn([$appId => ['customer:read']]);

        static::assertFalse((new AppCapability($privileges))->can($appId, Tax::PERMISSION));
    }

    public function testCanReturnsFalseWhenAppUnknown(): void
    {
        $appId = Uuid::randomHex();

        $privileges = static::createStub(Privileges::class);
        $privileges->method('getPrivileges')->willReturn([]);

        static::assertFalse((new AppCapability($privileges))->can($appId, Tax::PERMISSION));
    }

    public function testWhenGrantedRunsCallbackAndReturnsItsResult(): void
    {
        $appId = Uuid::randomHex();

        $privileges = static::createStub(Privileges::class);
        $privileges->method('getPrivileges')->willReturn([$appId => [Tax::PERMISSION]]);

        $result = (new AppCapability($privileges))->whenGranted($appId, Tax::PERMISSION, static fn (): string => 'dispatched');

        static::assertSame('dispatched', $result);
    }

    public function testWhenGrantedSkipsCallbackAndReturnsNullWhenNotGranted(): void
    {
        $appId = Uuid::randomHex();

        $privileges = static::createStub(Privileges::class);
        $privileges->method('getPrivileges')->willReturn([$appId => ['customer:read']]);

        $called = false;
        $result = (new AppCapability($privileges))->whenGranted($appId, Tax::PERMISSION, static function () use (&$called): string {
            $called = true;

            return 'dispatched';
        });

        static::assertNull($result);
        static::assertFalse($called, 'callback must not run when the permission is not granted');
    }
}
