<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\Requirement\ServiceConsentRequirement;
use Shopware\Core\Service\Subscriber\ServiceConsentSubscriber;
use Shopware\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopware\Core\System\Consent\Event\ConsentRevokedEvent;

/**
 * @internal
 */
#[CoversClass(ServiceConsentSubscriber::class)]
class ServiceConsentSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            ConsentAcceptedEvent::class => 'syncConsentRequirement',
            ConsentRevokedEvent::class => 'syncConsentRequirement',
        ], ServiceConsentSubscriber::getSubscribedEvents());
    }

    public function testSyncsRequirementForServicesConsent(): void
    {
        $manager = $this->createMock(LifecycleManager::class);
        $manager->expects($this->once())
            ->method('syncRequirement')
            ->with(
                ServiceConsentRequirement::NAME,
                $this->callback(static fn (Context $context): bool => $context->getScope() === Context::SYSTEM_SCOPE)
            );

        $subscriber = new ServiceConsentSubscriber($manager);
        $subscriber->syncConsentRequirement(new ConsentAcceptedEvent('service_consent', 'system', 'system', 'actor', '2026-05-05'));
    }

    public function testIgnoresOtherConsents(): void
    {
        $manager = $this->createMock(LifecycleManager::class);
        $manager->expects($this->never())->method('syncRequirement');

        $subscriber = new ServiceConsentSubscriber($manager);
        $subscriber->syncConsentRequirement(new ConsentRevokedEvent('other-consent', 'system', 'system', 'actor'));
    }
}
