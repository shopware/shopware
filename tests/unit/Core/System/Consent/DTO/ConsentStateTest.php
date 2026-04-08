<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\DTO;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\DTO\ConsentState;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(ConsentState::class)]
class ConsentStateTest extends TestCase
{
    public function testAcceptedUtilIsNowIfAccepted(): void
    {
        $consent = new ConsentState(
            'test_consent',
            'system',
            'system',
            ConsentStatus::ACCEPTED,
            'test_user',
            (new \DateTimeImmutable('2026-03-02'))->format(Defaults::STORAGE_DATE_TIME_FORMAT)
        );

        static::assertIsString($consent->acceptedUntil);
        static::assertEqualsWithDelta(
            (new \DateTimeImmutable())->getTimestamp(),
            (new \DateTimeImmutable($consent->acceptedUntil))->getTimestamp(),
            1
        );
    }

    #[DataProvider('consentStateProvider')]
    public function testAcceptedUntilWithAllConsentStates(ConsentStatus $state, ?string $expectedDate): void
    {
        $consent = new ConsentState(
            'test_consent',
            'system',
            'system',
            $state,
            'test_user',
            (new \DateTimeImmutable('2026-03-02'))->format(Defaults::STORAGE_DATE_TIME_FORMAT)
        );

        static::assertSame($expectedDate, $consent->acceptedUntil);
    }

    public static function consentStateProvider(): \Generator
    {
        yield 'Returns null if never accepted' => [ConsentStatus::UNSET, null];
        yield 'Returns null if initially declined' => [ConsentStatus::DECLINED, null];
        yield 'Returns updated_at when revoked later' => [ConsentStatus::REVOKED, (new \DateTimeImmutable('2026-03-02'))->format(Defaults::STORAGE_DATE_TIME_FORMAT)];
    }

    public function testAcceptedRevisionIsClearedForNonAcceptedStates(): void
    {
        $consent = new ConsentState(
            'test_consent',
            'system',
            'system',
            ConsentStatus::REVOKED,
            'test_user',
            (new \DateTimeImmutable('2026-03-02'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            '2.0.0',
            '2.0.0',
        );

        static::assertNull($consent->acceptedRevision);
        static::assertFalse($consent->isAccepted());
        static::assertFalse($consent->isCurrent());
        static::assertFalse($consent->isStale());
    }

    public function testAcceptedConsentCanBeDetectedAsStale(): void
    {
        $consent = new ConsentState(
            'test_consent',
            'system',
            'system',
            ConsentStatus::ACCEPTED,
            'test_user',
            (new \DateTimeImmutable('2026-03-02'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            '1.0.0',
            '2.0.0',
        );

        static::assertTrue($consent->isAccepted());
        static::assertFalse($consent->isCurrent());
        static::assertTrue($consent->isStale());
    }
}
