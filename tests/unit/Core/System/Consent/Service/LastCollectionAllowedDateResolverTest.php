<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\System\Consent\ConsentScope;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\Definition\BackendData;
use Shopware\Core\System\Consent\DTO\ConsentState;
use Shopware\Core\System\Consent\Service\ConsentService;
use Shopware\Core\System\Consent\Service\LastCollectionAllowedDateResolver;

/**
 * @internal
 */
#[CoversClass(LastCollectionAllowedDateResolver::class)]
class LastCollectionAllowedDateResolverTest extends TestCase
{
    public function testReturnsRevokedDateWhenConsentIsRevoked(): void
    {
        $updatedAt = new \DateTimeImmutable('2023-07-25T07:00:19.803422+0000');

        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects($this->once())
            ->method('getConsentState')
            ->willReturn($this->createConsentState(ConsentStatus::REVOKED, $updatedAt));

        $resolver = new LastCollectionAllowedDateResolver($consentService);

        $result = $resolver->getLastCollectionAllowedDate();
        static::assertInstanceOf(\DateTimeImmutable::class, $result);
        static::assertSame(
            $updatedAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            $result->format(Defaults::STORAGE_DATE_TIME_FORMAT)
        );
    }

    public function testReturnsNullWhenConsentWasNeverGiven(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects($this->once())
            ->method('getConsentState')
            ->willReturn($this->createConsentState(ConsentStatus::UNSET, null));

        $resolver = new LastCollectionAllowedDateResolver($consentService);

        static::assertNull($resolver->getLastCollectionAllowedDate());
    }

    public function testReturnsNowWhenConsentIsAccepted(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects($this->once())
            ->method('getConsentState')
            ->willReturn($this->createConsentState(ConsentStatus::ACCEPTED, null));

        $resolver = new LastCollectionAllowedDateResolver($consentService);

        $before = new \DateTimeImmutable();
        $result = $resolver->getLastCollectionAllowedDate();
        $after = new \DateTimeImmutable();

        static::assertInstanceOf(\DateTimeImmutable::class, $result);
        static::assertGreaterThanOrEqual($before->getTimestamp(), $result->getTimestamp());
        static::assertLessThanOrEqual($after->getTimestamp(), $result->getTimestamp());
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
