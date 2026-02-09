<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\System\Consent\ConsentScope;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\Definition\BackendData;
use Shopware\Core\System\Consent\DTO\ConsentState;
use Shopware\Core\System\Consent\Service\ConsentDateResolver;
use Shopware\Core\System\Consent\Service\ConsentService;

/**
 * @internal
 */
#[CoversClass(ConsentDateResolver::class)]
class ConsentDateResolverTest extends TestCase
{
    public function testReturnsNullWhenConsentIsRevoked(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects($this->once())
            ->method('getConsentState')
            ->willReturn($this->createConsentState(ConsentStatus::REVOKED, null));

        $resolver = new ConsentDateResolver($consentService);

        static::assertNull($resolver->getLastConsentAcceptedDate());
    }

    public function testReturnsNullWhenUpdatedAtIsMissing(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects($this->once())
            ->method('getConsentState')
            ->willReturn($this->createConsentState(ConsentStatus::ACCEPTED, null));

        $resolver = new ConsentDateResolver($consentService);

        static::assertNull($resolver->getLastConsentAcceptedDate());
    }

    public function testReturnsUpdatedAtWhenConsentIsAccepted(): void
    {
        $updatedAt = new \DateTimeImmutable('2023-07-25T07:00:19.803422+0000');

        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects($this->once())
            ->method('getConsentState')
            ->willReturn($this->createConsentState(ConsentStatus::ACCEPTED, $updatedAt));

        $resolver = new ConsentDateResolver($consentService);

        $result = $resolver->getLastConsentAcceptedDate();
        static::assertInstanceOf(\DateTimeImmutable::class, $result);
        static::assertSame(
            $updatedAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            $result->format(Defaults::STORAGE_DATE_TIME_FORMAT)
        );
    }

    private function createConsentState(ConsentStatus $status, ?\DateTimeImmutable $updatedAt): ConsentState
    {
        return new ConsentState(
            BackendData::NAME,
            ConsentScope\System::NAME,
            ConsentScope\System::NAME,
            $status,
            'actor',
            $updatedAt?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        );
    }
}
