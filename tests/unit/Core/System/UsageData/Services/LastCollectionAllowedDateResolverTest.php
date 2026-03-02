<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\System\Consent\ConsentScope;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\Definition\BackendData;
use Shopware\Core\System\Consent\DTO\ConsentState;
use Shopware\Core\System\Consent\Service\ConsentService;
use Shopware\Core\System\UsageData\Services\LastCollectionAllowedDateResolver;

/**
 * @internal
 */
#[CoversClass(LastCollectionAllowedDateResolver::class)]
class LastCollectionAllowedDateResolverTest extends TestCase
{
    public function testResolverReturnsNowIfConsentIsAccepted(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->method('getConsentState')->willReturn(
            $this->createConsentState(ConsentStatus::ACCEPTED, new \DateTimeImmutable('2026-02-26'))
        );

        $resolver = new LastCollectionAllowedDateResolver($consentService);
        $result = $resolver->getCollectUntil();

        static::assertInstanceOf(\DateTimeImmutable::class, $result);
        static::assertEqualsWithDelta((new \DateTimeImmutable())->getTimestamp(), $result->getTimestamp(), 1);
    }

    public function testReturnsUpdatedAtTimeIfConsentWasRevoked(): void
    {
        $updatedAt = new \DateTimeImmutable('2026-02-26');

        $consentService = $this->createMock(ConsentService::class);
        $consentService->method('getConsentState')->willReturn(
            $this->createConsentState(ConsentStatus::REVOKED, $updatedAt)
        );

        $resolver = new LastCollectionAllowedDateResolver($consentService);
        $result = $resolver->getCollectUntil();

        static::assertInstanceOf(\DateTimeImmutable::class, $result);
        static::assertEquals($updatedAt, $result);
    }

    public function testReturnsNullIfConsentIsNotSet(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->method('getConsentState')->willReturn(
            $this->createConsentState(ConsentStatus::UNSET, null)
        );

        $resolver = new LastCollectionAllowedDateResolver($consentService);
        $result = $resolver->getCollectUntil();

        static::assertNull($result);
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
