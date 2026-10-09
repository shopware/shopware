<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Requirement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Permission\PermissionsService;
use Shopware\Core\Service\Requirement\Gate;
use Shopware\Core\Service\Requirement\ServiceConsentRequirement;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ServiceConsentRequirement::class)]
class ServiceConsentRequirementTest extends TestCase
{
    public function testGetName(): void
    {
        static::assertSame('service_consent', ServiceConsentRequirement::getName());
    }

    public function testGatesPrivileges(): void
    {
        static::assertSame(Gate::PRIVILEGES, (new ServiceConsentRequirement(static::createStub(PermissionsService::class)))->getGate());
    }

    public function testPermitsStateChange(): void
    {
        static::assertTrue((new ServiceConsentRequirement(static::createStub(PermissionsService::class)))->permitsStateChange());
    }

    public function testIsSatisfiedWhenPermissionsAreGranted(): void
    {
        $permissionsService = $this->createMock(PermissionsService::class);
        $permissionsService->expects($this->once())
            ->method('areGranted')
            ->willReturn(true);

        $requirement = new ServiceConsentRequirement($permissionsService);

        static::assertTrue($requirement->isSatisfied());
    }

    public function testIsNotSatisfiedWhenPermissionsAreNotGranted(): void
    {
        $permissionsService = $this->createMock(PermissionsService::class);
        $permissionsService->expects($this->once())
            ->method('areGranted')
            ->willReturn(false);

        $requirement = new ServiceConsentRequirement($permissionsService);

        static::assertFalse($requirement->isSatisfied());
    }
}
