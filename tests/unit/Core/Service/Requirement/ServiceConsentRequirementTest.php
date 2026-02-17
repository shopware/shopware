<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Requirement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Service\Permission\PermissionsService;
use Shopware\Core\Service\Requirement\ServiceConsentRequirement;

/**
 * @internal
 */
#[CoversClass(ServiceConsentRequirement::class)]
class ServiceConsentRequirementTest extends TestCase
{
    public function testGetName(): void
    {
        static::assertSame('service_consent', ServiceConsentRequirement::getName());
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
