<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\Consent\Definition\BackendData;
use Shopware\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopware\Core\System\Consent\Event\ConsentRevokedEvent;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Services\EntityDispatchService;
use Shopware\Core\System\UsageData\Subscriber\ConsentStateChangedSubscriber;

/**
 * @internal
 */
#[CoversClass(ConsentStateChangedSubscriber::class)]
class ConsentStateChangedSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                ConsentAcceptedEvent::class => 'handleConsentAcceptedEvent',
                ConsentRevokedEvent::class => 'handleConsentRevokedEvent',
            ],
            ConsentStateChangedSubscriber::getSubscribedEvents()
        );
    }

    public function testAcceptConsentHandlesOnlyBackendData(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects($this->once())->method('acceptConsent');

        $entityDispatchService = $this->createMock(EntityDispatchService::class);
        $entityDispatchService->expects($this->once())->method('dispatchCollectEntityDataMessage');

        $subscriber = new ConsentStateChangedSubscriber($consentService, $entityDispatchService);

        $subscriber->handleConsentAcceptedEvent(new ConsentAcceptedEvent(
            BackendData::NAME,
            'system',
            'system',
            'actor'
        ));
    }

    public function testAcceptConsentIgnoresOtherConsentNames(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects($this->never())->method('acceptConsent');

        $entityDispatchService = $this->createMock(EntityDispatchService::class);
        $entityDispatchService->expects($this->never())->method('dispatchCollectEntityDataMessage');

        $subscriber = new ConsentStateChangedSubscriber($consentService, $entityDispatchService);

        $subscriber->handleConsentAcceptedEvent(new ConsentAcceptedEvent(
            'other-consent',
            'system',
            'system',
            'actor'
        ));
    }

    public function testRevokeConsentHandlesOnlyBackendData(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects($this->once())->method('revokeConsent');

        $entityDispatchService = $this->createMock(EntityDispatchService::class);
        $entityDispatchService->expects($this->never())->method('dispatchCollectEntityDataMessage');

        $subscriber = new ConsentStateChangedSubscriber($consentService, $entityDispatchService);

        $subscriber->handleConsentRevokedEvent(new ConsentRevokedEvent(
            BackendData::NAME,
            'system',
            'system',
            'actor'
        ));
    }

    public function testRevokeConsentIgnoresOtherConsentNames(): void
    {
        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects($this->never())->method('revokeConsent');

        $entityDispatchService = $this->createMock(EntityDispatchService::class);
        $entityDispatchService->expects($this->never())->method('dispatchCollectEntityDataMessage');

        $subscriber = new ConsentStateChangedSubscriber($consentService, $entityDispatchService);

        $subscriber->handleConsentRevokedEvent(new ConsentRevokedEvent(
            'other-consent',
            'system',
            'system',
            'actor'
        ));
    }
}
