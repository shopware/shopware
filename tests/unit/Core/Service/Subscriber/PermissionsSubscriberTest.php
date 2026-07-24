<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\Event\PermissionsGrantedEvent;
use Shopware\Core\Service\Event\PermissionsRevokedEvent;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\Permission\PermissionsConsent;
use Shopware\Core\Service\Requirement\ServiceConsentRequirement;
use Shopware\Core\Service\Subscriber\PermissionsSubscriber;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PermissionsSubscriber::class)]
class PermissionsSubscriberTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testSyncConsentRequirementOnGrant(): void
    {
        $consent = new PermissionsConsent(
            identifier: 'test-identifier',
            revision: '2025-06-13T00:00:00+00:00',
            consentingUserId: 'test-user-id',
            grantedAt: new \DateTime('2025-06-13')
        );
        $event = new PermissionsGrantedEvent($consent, $this->context);

        $manager = $this->createMock(LifecycleManager::class);
        $manager
            ->expects($this->once())
            ->method('reevaluateRequirement')
            ->with(ServiceConsentRequirement::NAME, $this->context);

        (new PermissionsSubscriber($manager))->syncConsentRequirement($event);
    }

    public function testSyncConsentRequirementOnRevoke(): void
    {
        $consent = new PermissionsConsent(
            identifier: 'test-identifier',
            revision: '2025-06-13T00:00:00+00:00',
            consentingUserId: 'test-user-id',
            grantedAt: new \DateTime('2025-06-13')
        );
        $event = new PermissionsRevokedEvent($consent, $this->context);

        $manager = $this->createMock(LifecycleManager::class);
        $manager
            ->expects($this->once())
            ->method('reevaluateRequirement')
            ->with(ServiceConsentRequirement::NAME, $this->context);

        (new PermissionsSubscriber($manager))->syncConsentRequirement($event);
    }

    public function testSubscribedEvents(): void
    {
        $events = PermissionsSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(PermissionsGrantedEvent::class, $events);
        static::assertArrayHasKey(PermissionsRevokedEvent::class, $events);
        static::assertSame('syncConsentRequirement', $events[PermissionsGrantedEvent::class]);
        static::assertSame('syncConsentRequirement', $events[PermissionsRevokedEvent::class]);
    }
}
