<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\FirstRunWizardService;
use Shopware\Core\Service\AllServiceInstaller;
use Shopware\Core\Service\Event\PermissionsGrantedEvent;
use Shopware\Core\Service\Event\PermissionsRevokedEvent;
use Shopware\Core\Service\Permission\PermissionsConsent;
use Shopware\Core\Service\ServiceLifecycle;
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

    #[TestWith([false, 1])]
    #[TestWith([true, 0])]
    public function testReevaluatesServicesAndSchedulesInstallOnGrantUnlessTheFirstRunWizardIsPending(bool $frwPending, int $scheduled): void
    {
        $consent = new PermissionsConsent(
            identifier: 'test-identifier',
            revision: '2025-06-13T00:00:00+00:00',
            consentingUserId: 'test-user-id',
            grantedAt: new \DateTime('2025-06-13')
        );
        $event = new PermissionsGrantedEvent($consent, $this->context);

        $serviceLifecycle = $this->createMock(ServiceLifecycle::class);
        $serviceLifecycle
            ->expects($this->once())
            ->method('reevaluateInstalled')
            ->with($this->context);

        $serviceInstaller = $this->createMock(AllServiceInstaller::class);
        $serviceInstaller->expects($this->exactly($scheduled))->method('scheduleInstall');

        $firstRunWizardService = static::createStub(FirstRunWizardService::class);
        $firstRunWizardService->method('frwShouldRun')->willReturn($frwPending);

        (new PermissionsSubscriber($serviceLifecycle, $serviceInstaller, $firstRunWizardService))->onGranted($event);
    }

    public function testReevaluatesServicesOnRevoke(): void
    {
        $consent = new PermissionsConsent(
            identifier: 'test-identifier',
            revision: '2025-06-13T00:00:00+00:00',
            consentingUserId: 'test-user-id',
            grantedAt: new \DateTime('2025-06-13')
        );
        $event = new PermissionsRevokedEvent($consent, $this->context);

        $serviceLifecycle = $this->createMock(ServiceLifecycle::class);
        $serviceLifecycle
            ->expects($this->once())
            ->method('reevaluateInstalled')
            ->with($this->context);

        $serviceInstaller = $this->createMock(AllServiceInstaller::class);
        $serviceInstaller->expects($this->never())->method('scheduleInstall');

        (new PermissionsSubscriber($serviceLifecycle, $serviceInstaller, static::createStub(FirstRunWizardService::class)))->reevaluateServices($event);
    }

    public function testSubscribedEvents(): void
    {
        $events = PermissionsSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(PermissionsGrantedEvent::class, $events);
        static::assertArrayHasKey(PermissionsRevokedEvent::class, $events);
        static::assertSame('onGranted', $events[PermissionsGrantedEvent::class]);
        static::assertSame('reevaluateServices', $events[PermissionsRevokedEvent::class]);
    }
}
