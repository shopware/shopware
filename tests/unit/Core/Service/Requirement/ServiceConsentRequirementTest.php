<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Requirement;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Service\Requirement\ServiceConsentRequirement;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\DTO\ConsentState;
use Shopware\Core\System\Consent\Service\ConsentService;

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
        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects($this->once())
            ->method('getConsentState')
            ->willReturn(new ConsentState(
                'service_consent',
                'system',
                'system',
                ConsentStatus::ACCEPTED,
                'user-id',
                '2026-05-05 12:00:00.000',
                '2026-05-05',
                '2026-05-05',
            ));

        $requirement = new ServiceConsentRequirement($consentService);

        static::assertTrue($requirement->isSatisfied());
    }

    public function testIsNotSatisfiedWhenPermissionsAreNotGranted(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects($this->once())
            ->method('getConsentState')
            ->willReturn(new ConsentState(
                'service_consent',
                'system',
                'system',
                ConsentStatus::REVOKED,
                'user-id',
                '2026-05-05 12:00:00.000',
                null,
                '2026-05-05',
            ));

        $requirement = new ServiceConsentRequirement($consentService);

        static::assertFalse($requirement->isSatisfied());
    }

    public function testIsNotSatisfiedWhenConsentLookupFails(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects($this->once())
            ->method('getConsentState')
            ->willThrowException(new \RuntimeException('boom'));

        $requirement = new ServiceConsentRequirement($consentService);

        static::assertFalse($requirement->isSatisfied());
    }
}
