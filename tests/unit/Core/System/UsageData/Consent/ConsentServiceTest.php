<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Consent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Consent\ConsentState;
use Shopware\Core\System\UsageData\Consent\ConsentStateChangedEvent;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyAcceptedException;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyRequestedException;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyRevokedException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\MockClock;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(ConsentService::class)]
class ConsentServiceTest extends TestCase
{
    public function testIsApprovalGivenReturnsConfigValue(): void
    {
        $systemConfig = new StaticSystemConfigService([
            ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::ACCEPTED->value,
        ]);

        $consentService = new ConsentService(
            $systemConfig,
            new StaticEntityRepository([]),
            new CollectingEventDispatcher(),
            new MockClock(),
        );

        static::assertTrue($consentService->isConsentAccepted());

        $systemConfig->set(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE, false);

        static::assertFalse($consentService->isConsentAccepted());
    }

    public function testIsApprovalGivenIsFalseIfConfigValueIsNotSet(): void
    {
        $consentService = new ConsentService(
            new StaticSystemConfigService(),
            new StaticEntityRepository([]),
            new CollectingEventDispatcher(),
            new MockClock(),
        );

        static::assertFalse($consentService->isConsentAccepted());
    }

    public function testThrowsIfConsentHasAlreadyBeenRequested(): void
    {
        $eventDispatcher = new CollectingEventDispatcher();

        $consentService = new ConsentService(
            new StaticSystemConfigService([
                ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::REQUESTED->value,
            ]),
            $this->createMock(EntityRepository::class),
            $eventDispatcher,
            new MockClock(),
        );

        static::assertEmpty($eventDispatcher->getEvents());
        static::expectException(ConsentAlreadyRequestedException::class);
        $consentService->requestConsent();
    }

    public function testStoresAndReportsConsentStateWhenRequestedForTheFirstTime(): void
    {
        $eventDispatcher = new CollectingEventDispatcher();
        $systemConfigService = new StaticSystemConfigService();

        $consentService = new ConsentService(
            $systemConfigService,
            $this->createMock(EntityRepository::class),
            $eventDispatcher,
            new MockClock(),
        );

        $consentService->requestConsent();

        $this->assertConsentEventFired($eventDispatcher, ConsentState::REQUESTED);
        static::assertSame(
            $systemConfigService->getString(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE),
            ConsentState::REQUESTED->value
        );
    }

    public function testStoresAndReportsConsentStateWhenAccepted(): void
    {
        $eventDispatcher = new CollectingEventDispatcher();
        $systemConfigService = new StaticSystemConfigService();

        $consentService = new ConsentService(
            $systemConfigService,
            $this->createMock(EntityRepository::class),
            $eventDispatcher,
            new MockClock(),
        );

        $consentService->acceptConsent();

        $this->assertConsentEventFired($eventDispatcher, ConsentState::ACCEPTED);
        static::assertSame(
            $systemConfigService->getString(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE),
            ConsentState::ACCEPTED->value
        );
    }

    public function testStoresAndReportsConsentStateWhenRevoked(): void
    {
        $eventDispatcher = new CollectingEventDispatcher();
        $systemConfigService = new StaticSystemConfigService([]);

        $consentService = new ConsentService(
            $systemConfigService,
            $this->createMock(EntityRepository::class),
            $eventDispatcher,
            new MockClock(),
        );

        $consentService->revokeConsent();
        $this->assertConsentEventFired($eventDispatcher, ConsentState::REVOKED);
        static::assertSame(
            $systemConfigService->getString(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE),
            ConsentState::REVOKED->value
        );
    }

    public function testDoesNotReportConsentStateChangeIfStateIsTheSameAsBefore(): void
    {
        $eventDispatcher = new CollectingEventDispatcher();
        $systemConfigService = new StaticSystemConfigService([
            ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::ACCEPTED->value,
        ]);

        $consentService = new ConsentService(
            $systemConfigService,
            $this->createMock(EntityRepository::class),
            $eventDispatcher,
            new MockClock(),
        );

        static::expectException(ConsentAlreadyAcceptedException::class);
        $consentService->acceptConsent();
    }

    public function testIgnoresExceptionsDuringReportingConsentState(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(static::once())
            ->method('dispatch')
            ->willThrowException(new \Exception());

        $systemConfigService = new StaticSystemConfigService([
            ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::ACCEPTED->value,
        ]);

        $consentService = new ConsentService(
            $systemConfigService,
            $this->createMock(EntityRepository::class),
            $eventDispatcher,
            new MockClock(),
        );

        $consentService->revokeConsent();

        static::assertSame(
            ConsentState::REVOKED->value,
            $systemConfigService->getString(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE),
        );
    }

    public function testHasNoConsentState(): void
    {
        $consentService = new ConsentService(
            new StaticSystemConfigService(),
            $this->createMock(EntityRepository::class),
            new CollectingEventDispatcher(),
            new MockClock(),
        );

        static::assertFalse($consentService->hasConsentState());
    }

    public function testHasConsentState(): void
    {
        foreach (ConsentState::cases() as $consentState) {
            $consentService = new ConsentService(
                new StaticSystemConfigService([
                    ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => $consentState->value,
                ]),
                $this->createMock(EntityRepository::class),
                new CollectingEventDispatcher(),
                new MockClock(),
            );

            static::assertTrue($consentService->hasConsentState());
        }
    }

    public function testConsentIsAccepted(): void
    {
        $consentService = new ConsentService(
            new StaticSystemConfigService([
                ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::ACCEPTED->value,
            ]),
            $this->createMock(EntityRepository::class),
            new CollectingEventDispatcher(),
            new MockClock(),
        );

        static::assertTrue($consentService->isConsentAccepted());
    }

    public function testThrowsIfConsentIsAlreadyRevoked(): void
    {
        $consentService = new ConsentService(
            new StaticSystemConfigService([
                ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::REVOKED->value,
            ]),
            $this->createMock(EntityRepository::class),
            new CollectingEventDispatcher(),
            new MockClock(),
        );

        static::expectException(ConsentAlreadyRevokedException::class);
        $consentService->revokeConsent();
    }

    public function testGetLastApprovalDateReturnsCurrentDateTime(): void
    {
        $systemConfig = new StaticSystemConfigService([
            ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::ACCEPTED->value,
        ]);

        $currentTime = new \DateTimeImmutable();

        $consentService = new ConsentService(
            $systemConfig,
            $this->createMock(EntityRepository::class),
            new CollectingEventDispatcher(),
            new MockClock($currentTime),
        );

        static::assertEquals($currentTime, $consentService->getLastConsentIsAcceptedDate());
    }

    public function testGetLastApprovalDateReturnsNullWhenApprovalWasNeverGiven(): void
    {
        $systemConfig = new StaticSystemConfigService([
            ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::REQUESTED->value,
        ]);

        $entitySearchResult = $this->createMock(EntitySearchResult::class);
        $entitySearchResult->method('first')
            ->willReturn(null);

        $systemConfigRepository = $this->createMock(EntityRepository::class);
        $systemConfigRepository->method('search')
            ->willReturn($entitySearchResult);

        $consentService = new ConsentService(
            $systemConfig,
            $systemConfigRepository,
            new CollectingEventDispatcher(),
            new MockClock(),
        );

        static::assertNull($consentService->getLastConsentIsAcceptedDate());
    }

    public function testGetLastApprovalDateReturnsLastSystemConfigUpdatedAtDateTime(): void
    {
        $systemConfig = new StaticSystemConfigService([
            ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::REVOKED->value,
        ]);

        $updatedAt = new \DateTimeImmutable('2021-01-01 00:00:00');
        $systemConfigEntity = new SystemConfigEntity();
        $systemConfigEntity->setUpdatedAt($updatedAt);

        $entitySearchResult = $this->createMock(EntitySearchResult::class);
        $entitySearchResult->method('first')
            ->willReturn($systemConfigEntity);

        $systemConfigRepository = $this->createMock(EntityRepository::class);
        $systemConfigRepository->method('search')
            ->willReturn($entitySearchResult);

        $consentService = new ConsentService(
            $systemConfig,
            $systemConfigRepository,
            new CollectingEventDispatcher(),
            new MockClock(),
        );

        static::assertEquals($updatedAt, $consentService->getLastConsentIsAcceptedDate());
    }

    private function assertConsentEventFired(CollectingEventDispatcher $dispatcher, ConsentState $state): void
    {
        $events = $dispatcher->getEvents();
        static::assertCount(1, $events);

        $consentChangedEvent = $events[0];
        static::assertInstanceOf(ConsentStateChangedEvent::class, $consentChangedEvent);
        static::assertEquals($state, $consentChangedEvent->getState());
    }
}
