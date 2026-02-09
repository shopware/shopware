<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Consent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Consent\ConsentState;
use Shopware\Core\System\UsageData\Consent\ConsentStateChangedEvent;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyAcceptedException;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyRequestedException;
use Shopware\Core\System\UsageData\Exception\ConsentAlreadyRevokedException;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

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
            new CollectingEventDispatcher(),
        );

        static::assertTrue($consentService->isConsentAccepted());

        $systemConfig->set(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE, false);

        static::assertFalse($consentService->isConsentAccepted());
    }

    public function testIsApprovalGivenIsFalseIfConfigValueIsNotSet(): void
    {
        $consentService = new ConsentService(
            new StaticSystemConfigService(),
            new CollectingEventDispatcher(),
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
            $eventDispatcher,
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
            $eventDispatcher,
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
            $eventDispatcher,
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
            $eventDispatcher,
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
            $eventDispatcher,
        );

        static::expectException(ConsentAlreadyAcceptedException::class);
        $consentService->acceptConsent();
    }

    public function testHasNoConsentState(): void
    {
        $consentService = new ConsentService(
            new StaticSystemConfigService(),
            new CollectingEventDispatcher(),
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
                new CollectingEventDispatcher(),
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
            new CollectingEventDispatcher(),
        );

        static::assertTrue($consentService->isConsentAccepted());
    }

    public function testThrowsIfConsentIsAlreadyRevoked(): void
    {
        $consentService = new ConsentService(
            new StaticSystemConfigService([
                ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::REVOKED->value,
            ]),
            new CollectingEventDispatcher(),
        );

        static::expectException(ConsentAlreadyRevokedException::class);
        $consentService->revokeConsent();
    }

    private function assertConsentEventFired(CollectingEventDispatcher $dispatcher, ConsentState $state): void
    {
        $events = $dispatcher->getEvents();
        static::assertCount(1, $events);

        $consentChangedEvent = $events[0];
        static::assertInstanceOf(ConsentStateChangedEvent::class, $consentChangedEvent);
        static::assertSame($state, $consentChangedEvent->getState());
    }
}
