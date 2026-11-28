<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\ServiceLifecycle;
use Shopware\Core\Service\Subscriber\ServiceConsentSubscriber;
use Shopware\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopware\Core\System\Consent\Event\ConsentRevokedEvent;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ServiceConsentSubscriber::class)]
class ServiceConsentSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            ConsentAcceptedEvent::class => 'reevaluateServices',
            ConsentRevokedEvent::class => 'reevaluateServices',
        ], ServiceConsentSubscriber::getSubscribedEvents());
    }

    public function testReevaluatesServicesWhenTheServiceConsentIsAccepted(): void
    {
        $serviceLifecycle = $this->createMock(ServiceLifecycle::class);
        $serviceLifecycle->expects($this->once())->method('reevaluateInstalled');

        $subscriber = new ServiceConsentSubscriber($serviceLifecycle);
        $subscriber->reevaluateServices(new ConsentAcceptedEvent('service_consent', 'system', 'system', 'actor', '2026-05-05'));
    }

    public function testReevaluatesServicesWhenTheServiceConsentIsRevoked(): void
    {
        $serviceLifecycle = $this->createMock(ServiceLifecycle::class);
        $serviceLifecycle->expects($this->once())->method('reevaluateInstalled');

        $subscriber = new ServiceConsentSubscriber($serviceLifecycle);
        $subscriber->reevaluateServices(new ConsentRevokedEvent('service_consent', 'system', 'system', 'actor'));
    }

    public function testIgnoresOtherConsents(): void
    {
        $serviceLifecycle = $this->createMock(ServiceLifecycle::class);
        $serviceLifecycle->expects($this->never())->method('reevaluateInstalled');

        $subscriber = new ServiceConsentSubscriber($serviceLifecycle);
        $subscriber->reevaluateServices(new ConsentRevokedEvent('other-consent', 'system', 'system', 'actor'));
    }
}
