<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\MethodSettingsService;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[CoversClass(MethodSettingsService::class)]
class MethodSettingsServiceTest extends TestCase
{
    private const CONFIG_KEY = 'core.adminAuth.methods';

    public function testDefaultsWhenNothingStored(): void
    {
        $service = new MethodSettingsService(new StaticSystemConfigService());

        $byType = $this->byType($service->all());

        static::assertCount(4, $byType);
        static::assertTrue($byType['password']['enabled']);
        static::assertTrue($byType['password']['isPrimary']);
        static::assertFalse($byType['password']['isSecondFactor']);
        static::assertFalse($byType['totp']['isPrimary']);
        static::assertTrue($byType['totp']['isSecondFactor']);
        static::assertTrue($byType['recovery_codes']['fallback']);
    }

    public function testCapabilityClampingOnRead(): void
    {
        // Try to make TOTP primary and password a second factor — both must be clamped off.
        $service = new MethodSettingsService(new StaticSystemConfigService([
            self::CONFIG_KEY => [
                'totp' => ['enabled' => true, 'isPrimary' => true, 'isSecondFactor' => true, 'priority' => 80],
                'password' => ['enabled' => true, 'isPrimary' => true, 'isSecondFactor' => true, 'priority' => 100],
            ],
        ]));

        $byType = $this->byType($service->all());

        static::assertFalse($byType['totp']['isPrimary'], 'totp cannot be primary');
        static::assertFalse($byType['password']['isSecondFactor'], 'password cannot be a second factor');
    }

    public function testAllIsOrderedByPriorityDesc(): void
    {
        $service = new MethodSettingsService(new StaticSystemConfigService());

        $priorities = array_map(static fn (array $m): int => $m['priority'], $service->all());
        $sorted = $priorities;
        rsort($sorted);

        static::assertSame($sorted, $priorities);
    }

    public function testIsEnabledAndRoleQueries(): void
    {
        $service = new MethodSettingsService(new StaticSystemConfigService([
            self::CONFIG_KEY => ['totp' => ['enabled' => false, 'isSecondFactor' => true, 'priority' => 80]],
        ]));

        static::assertFalse($service->isEnabled('totp'));
        static::assertFalse($service->isSecondFactor('totp'), 'disabled method is not a usable second factor');
        static::assertTrue($service->isPrimary('password'));
        static::assertTrue($service->isFallback('recovery_codes'));
        static::assertFalse($service->isFallback('totp'));
    }

    public function testEnabledSecondFactorsExcludesDisabled(): void
    {
        $service = new MethodSettingsService(new StaticSystemConfigService([
            self::CONFIG_KEY => ['webauthn' => ['enabled' => false, 'isPrimary' => true, 'isSecondFactor' => true, 'priority' => 90]],
        ]));

        $factors = $service->enabledSecondFactors();

        static::assertNotContains('webauthn', $factors);
        static::assertContains('totp', $factors);
        static::assertContains('recovery_codes', $factors);
        static::assertNotContains('password', $factors);
    }

    public function testYamlGateDisablesMethodNonOverridably(): void
    {
        // TOTP disabled via shopware.admin_auth.mfa.methods, but system config tries to enable it.
        $service = new MethodSettingsService(
            new StaticSystemConfigService([
                self::CONFIG_KEY => ['totp' => ['enabled' => true, 'isSecondFactor' => true, 'priority' => 80]],
            ]),
            ['totp' => false, 'webauthn' => true, 'recovery_codes' => true],
        );

        static::assertFalse($service->isEnabled('totp'), 'YAML false wins over system config');
        static::assertNotContains('totp', $service->enabledSecondFactors());
        static::assertContains('webauthn', $service->enabledSecondFactors());
    }

    public function testYamlGateTrueStillAllowsRuntimeDisable(): void
    {
        $service = new MethodSettingsService(
            new StaticSystemConfigService([
                self::CONFIG_KEY => ['totp' => ['enabled' => false, 'isSecondFactor' => true, 'priority' => 80]],
            ]),
            ['totp' => true, 'webauthn' => true, 'recovery_codes' => true],
        );

        static::assertFalse($service->isEnabled('totp'), 'system config may disable a YAML-enabled method');
    }

    public function testPasswordLoginConfigGatesPasswordMethod(): void
    {
        $service = new MethodSettingsService(new StaticSystemConfigService(), [], false);

        static::assertFalse($service->isEnabled('password'));
        static::assertFalse($service->isPrimary('password'));
        static::assertTrue($service->isEnabled('totp'), 'password_login only gates the password method');
    }

    public function testSaveClampsAndPersistsKnownMethodsOnly(): void
    {
        $config = new StaticSystemConfigService();
        $service = new MethodSettingsService($config);

        $service->save([
            'totp' => ['enabled' => true, 'isPrimary' => true, 'isSecondFactor' => true, 'priority' => 50],
            'bogus' => ['enabled' => true],
        ]);

        $captured = $config->get(self::CONFIG_KEY);

        static::assertIsArray($captured);
        static::assertArrayHasKey('totp', $captured);
        static::assertArrayNotHasKey('bogus', $captured, 'unknown methods are dropped');
        static::assertIsArray($captured['totp']);
        static::assertFalse($captured['totp']['isPrimary'], 'totp primary clamped off on save');
        static::assertSame(50, $captured['totp']['priority']);
        static::assertCount(4, $captured, 'all four built-ins are always persisted');
    }

    /**
     * @param list<array<string, mixed>> $all
     *
     * @return array<string, array<string, mixed>>
     */
    private function byType(array $all): array
    {
        $byType = [];
        foreach ($all as $m) {
            $byType[$m['type']] = $m;
        }

        return $byType;
    }
}
