<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Consent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Consent\AppConsentDefinition;
use Shopware\Core\Framework\App\Consent\ConsentConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppConsentDefinition::class)]
class AppConsentDefinitionTest extends TestCase
{
    public function testSystemScopedConsent(): void
    {
        $definition = $this->createDefinition('system');

        static::assertSame('swagApp-order_analysis', $definition->getName());
        static::assertSame('system', $definition->getScopeName());
        static::assertSame(['system.system_config'], $definition->getRequiredPermissions());
        static::assertSame('2026-01-01', $definition->getLatestRevision());
        static::assertSame('2026-02-03', $definition->getSince()->format('Y-m-d'));
    }

    public function testAdminUserScopedConsent(): void
    {
        $definition = $this->createDefinition('admin_user');

        static::assertSame('admin_user', $definition->getScopeName());
        static::assertSame(['user.update_profile'], $definition->getRequiredPermissions());
    }

    public function testConsentWithoutRevision(): void
    {
        $definition = $this->createDefinition('system', null);

        static::assertNull($definition->getLatestRevision());
    }

    private function createDefinition(string $scope, ?string $revision = '2026-01-01'): AppConsentDefinition
    {
        return new AppConsentDefinition(
            'swagApp',
            new ConsentConfig('order_analysis', $scope, $revision),
            new \DateTimeImmutable('2026-02-03 10:00:00'),
        );
    }
}
