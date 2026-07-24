<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Requirement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\Requirement\Gate;
use Shopware\Core\Service\Requirement\ServicesEnabledRequirement;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ServicesEnabledRequirement::class)]
class ServicesEnabledRequirementTest extends TestCase
{
    public function testGetName(): void
    {
        static::assertSame('services_enabled', ServicesEnabledRequirement::getName());
    }

    public function testGatesInstallation(): void
    {
        static::assertSame(Gate::INSTALLATION, (new ServicesEnabledRequirement(new StaticSystemConfigService()))->getGate());
    }

    public function testPermitsStateChange(): void
    {
        static::assertTrue((new ServicesEnabledRequirement(new StaticSystemConfigService()))->permitsStateChange());
    }

    public function testIsSatisfiedWhenSystemConfigDoesNotDisableServices(): void
    {
        $requirement = new ServicesEnabledRequirement(new StaticSystemConfigService());

        static::assertTrue($requirement->isSatisfied());
    }

    public function testIsSatisfiedWhenSystemConfigExplicitlyEnablesServices(): void
    {
        $requirement = new ServicesEnabledRequirement(
            new StaticSystemConfigService([LifecycleManager::CONFIG_KEY_SERVICES_DISABLED => false])
        );

        static::assertTrue($requirement->isSatisfied());
    }

    public function testIsNotSatisfiedWhenDisabledBySystemConfig(): void
    {
        $requirement = new ServicesEnabledRequirement(
            new StaticSystemConfigService([LifecycleManager::CONFIG_KEY_SERVICES_DISABLED => true])
        );

        static::assertFalse($requirement->isSatisfied());
    }
}
