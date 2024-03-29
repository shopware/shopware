<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Core\System\SystemConfig\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedHook;

/**
 * @internal
 */
#[CoversClass(SystemConfigChangedHook::class)]
class SystemConfigChangedHookTest extends TestCase
{
    public function testName(): void
    {
        static::assertSame('app.config.changed', (new SystemConfigChangedHook([], []))->getName());
    }

    /**
     * @param array<string> $permissions
     */
    #[DataProvider('getPermissionCases')]
    public static function testPermissions(SystemConfigChangedHook $hook, array $permissions, bool $allowed): void
    {
        static::assertSame($allowed, $hook->isAllowed('app-id', new AclPrivilegeCollection($permissions)));
    }

    public function testGetWebhookPayloadWithApp(): void
    {
        $hook = new SystemConfigChangedHook(['app.foo' => 'bar', 'bla.test' => 'bla'], ['app-id' => 'app']);
        $app = new AppEntity();
        $app->setName('app');

        static::assertSame(['app.foo'], $hook->getWebhookPayload($app)['changes']);
    }

    public function testGetWebhookPayloadGeneric(): void
    {
        $hook = new SystemConfigChangedHook(['app.foo' => 'bar', 'bla.test' => 'bla'], ['app-id' => 'app']);

        static::assertSame(['app.foo', 'bla.test'], $hook->getWebhookPayload()['changes']);
    }

    public static function getPermissionCases(): \Generator
    {
        yield 'no permissions' => [
            new SystemConfigChangedHook([], []),
            [],
            false,
        ];

        yield 'with permissions' => [
            new SystemConfigChangedHook(['app.foo' => 'bar'], ['app-id' => 'app']),
            ['system_config:read'],
            true,
        ];

        yield 'different app' => [
            new SystemConfigChangedHook(['app.foo' => 'bar'], ['app-id' => 'app2']),
            ['system_config:read'],
            false,
        ];

        yield 'app not installed' => [
            new SystemConfigChangedHook(['app.foo' => 'bar'], ['app2-id' => 'app2']),
            ['system_config:read'],
            false,
        ];
    }
}
