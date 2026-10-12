<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;
use Shopware\Core\Service\Event\CommercialLicenseProvidedEvent;
use Shopware\Core\Service\Subscriber\LicenseProviderSubscriber;
use Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LicenseProviderSubscriber::class)]
class LicenseProviderSubscriberTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    private LicenseProviderSubscriber $subscriber;

    /**
     * @var array<CommercialLicenseProvidedEvent>
     */
    private array $providedEvents = [];

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->eventDispatcher->addListener(CommercialLicenseProvidedEvent::class, function (CommercialLicenseProvidedEvent $event): void {
            $this->providedEvents[] = $event;
        });
        $this->subscriber = $this->createSubscriber();
    }

    public function testGetSubscribedEventsReturnsCorrectEvents(): void
    {
        static::assertSame([
            AppActivatedEvent::class => 'serviceActivated',
            BeforeSystemConfigChangedEvent::class => 'licenseChanged',
        ], LicenseProviderSubscriber::getSubscribedEvents());
    }

    public function testLicenseIsProvidedWhenLicenseKeyChanges(): void
    {
        $this->subscriber = $this->createSubscriber([
            LicenseProviderSubscriber::CONFIG_STORE_LICENSE_KEY => 'old-license-key',
            LicenseProviderSubscriber::CONFIG_STORE_LICENSE_HOST => 'license-host',
        ]);

        $this->subscriber->licenseChanged(new BeforeSystemConfigChangedEvent(
            LicenseProviderSubscriber::CONFIG_STORE_LICENSE_KEY,
            'new-license-key',
            null,
        ));

        static::assertCount(1, $this->providedEvents);
        static::assertSame([
            'licenseKey' => 'new-license-key',
            'licenseHost' => 'license-host',
        ], $this->providedEvents[0]->getWebhookPayload());
    }

    public function testLicenseIsProvidedWhenLicenseHostChanges(): void
    {
        $this->subscriber = $this->createSubscriber([
            LicenseProviderSubscriber::CONFIG_STORE_LICENSE_KEY => 'license-key',
            LicenseProviderSubscriber::CONFIG_STORE_LICENSE_HOST => 'old-license-host',
        ]);

        $this->subscriber->licenseChanged(new BeforeSystemConfigChangedEvent(
            LicenseProviderSubscriber::CONFIG_STORE_LICENSE_HOST,
            'new-license-host',
            null,
        ));

        static::assertCount(1, $this->providedEvents);
        static::assertSame([
            'licenseKey' => 'license-key',
            'licenseHost' => 'new-license-host',
        ], $this->providedEvents[0]->getWebhookPayload());
    }

    public function testLicenseIsNotProvidedForIrrelevantConfigKey(): void
    {
        $this->subscriber->licenseChanged(new BeforeSystemConfigChangedEvent(
            'irrelevant.config',
            'license-key',
            null,
        ));

        static::assertCount(0, $this->providedEvents);
    }

    public function testLicenseIsNotProvidedWhenValueIsNotString(): void
    {
        $this->subscriber->licenseChanged(new BeforeSystemConfigChangedEvent(
            LicenseProviderSubscriber::CONFIG_STORE_LICENSE_KEY,
            null,
            null,
        ));

        static::assertCount(0, $this->providedEvents);
    }

    public function testLicenseIsNotProvidedWhenValueHasNotChanged(): void
    {
        $this->subscriber = $this->createSubscriber([
            LicenseProviderSubscriber::CONFIG_STORE_LICENSE_KEY => 'license-key',
        ]);

        $this->subscriber->licenseChanged(new BeforeSystemConfigChangedEvent(
            LicenseProviderSubscriber::CONFIG_STORE_LICENSE_KEY,
            'license-key',
            null,
        ));

        static::assertCount(0, $this->providedEvents);
    }

    public function testLicenseIsProvidedForActivatedService(): void
    {
        $this->subscriber = $this->createSubscriber([
            LicenseProviderSubscriber::CONFIG_STORE_LICENSE_KEY => 'license-key',
            LicenseProviderSubscriber::CONFIG_STORE_LICENSE_HOST => 'license-host',
        ]);

        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('service-app');
        $app->setSelfManaged(true);

        $this->subscriber->serviceActivated(new AppActivatedEvent($app, Context::createDefaultContext()));

        static::assertCount(1, $this->providedEvents);
        static::assertSame([
            'licenseKey' => 'license-key',
            'licenseHost' => 'license-host',
        ], $this->providedEvents[0]->getWebhookPayload());
        static::assertTrue($this->providedEvents[0]->isAllowed($app->getId(), new AclPrivilegeCollection([])));
        static::assertFalse($this->providedEvents[0]->isAllowed(Uuid::randomHex(), new AclPrivilegeCollection([])));
    }

    public function testLicenseIsNotProvidedForActivatedRegularApp(): void
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('regular-app');
        $app->setSelfManaged(false);

        $this->subscriber->serviceActivated(new AppActivatedEvent($app, Context::createDefaultContext()));

        static::assertCount(0, $this->providedEvents);
    }

    /**
     * @param array<string, string> $config
     */
    private function createSubscriber(array $config = []): LicenseProviderSubscriber
    {
        return new LicenseProviderSubscriber(
            new StaticSystemConfigService($config),
            $this->eventDispatcher,
        );
    }
}
