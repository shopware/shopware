<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseUpdater;
use Shopware\Core\Framework\Store\Subscriber\LicenseHostChangedSubscriber;
use Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopware\Core\System\SystemConfig\Event\SystemConfigDomainLoadedEvent;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(LicenseHostChangedSubscriber::class)]
class LicenseHostChangedSubscriberTest extends TestCase
{
    public function testIsSubscribedToSystemConfigChangedEvents(): void
    {
        static::assertSame([
            BeforeSystemConfigChangedEvent::class => 'onLicenseHostChanged',
            SystemConfigChangedEvent::class => 'updateIapKey',
            SystemConfigDomainLoadedEvent::class => 'removeIapInformationFromDomain',
        ], LicenseHostChangedSubscriber::getSubscribedEvents());
    }

    public function testOnLicenseHostChangedOnlyUsesLicenseHost(): void
    {
        $config = new StaticSystemConfigService([
            'core.store.shopSecret' => 'shop-s3cr3t',
        ]);
        $subscriber = new LicenseHostChangedSubscriber(
            $config,
            $this->createMock(Connection::class),
            $this->createMock(InAppPurchaseUpdater::class),
        );

        $event = new BeforeSystemConfigChangedEvent('random.config.key', null, null);

        $subscriber->onLicenseHostChanged($event);
        static::assertSame($config->get('core.store.shopSecret'), 'shop-s3cr3t');
    }

    public function testOnLicenseHostChangedOnlyHandlesModifiedValue(): void
    {
        $config = new StaticSystemConfigService([
            'core.store.shopSecret' => 'shop-s3cr3t',
            'core.store.licenseHost' => 'host',
        ]);
        $subscriber = new LicenseHostChangedSubscriber(
            $config,
            $this->createMock(Connection::class),
            $this->createMock(InAppPurchaseUpdater::class),
        );

        $event = new BeforeSystemConfigChangedEvent('core.store.licenseHost', 'host', null);

        $subscriber->onLicenseHostChanged($event);
        static::assertSame($config->get('core.store.shopSecret'), 'shop-s3cr3t');
    }

    public function testDeletesShopSecretAndLogsOutAllUsers(): void
    {
        $config = new StaticSystemConfigService([
            'core.store.shopSecret' => 'shop-s3cr3t',
            'core.store.licenseHost' => 'host',
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())->method('executeStatement')->with('UPDATE user SET store_token = NULL');

        $subscriber = new LicenseHostChangedSubscriber($config, $connection, $this->createMock(InAppPurchaseUpdater::class));

        $event = new BeforeSystemConfigChangedEvent('core.store.licenseHost', 'otherhost', null);
        $subscriber->onLicenseHostChanged($event);

        static::assertNull($config->get('core.store.shopSecret'));
        static::assertNull($config->get('core.store.iapKey'));
    }

    public function testUpdateIapKeyOnlyUsesStoreToken(): void
    {
        $iapUpdater = $this->createMock(InAppPurchaseUpdater::class);
        $iapUpdater->expects(static::never())->method('update');

        $subscriber = new LicenseHostChangedSubscriber(
            new StaticSystemConfigService(),
            $this->createMock(Connection::class),
            $iapUpdater,
        );

        $event = new SystemConfigChangedEvent('random.config.key', 'whatever', null);
        $subscriber->updateIapKey($event);
    }

    public function testUpdateIapKeyOnlyUsesActualToken(): void
    {
        $iapUpdater = $this->createMock(InAppPurchaseUpdater::class);
        $iapUpdater->expects(static::never())->method('update');

        $subscriber = new LicenseHostChangedSubscriber(
            new StaticSystemConfigService(),
            $this->createMock(Connection::class),
            $iapUpdater,
        );

        $event = new SystemConfigChangedEvent('core.store.shopSecret', null, null);
        $subscriber->updateIapKey($event);
    }

    public function testUpdateIapKeyUpdatesOnStoreSecretSet(): void
    {
        $iapUpdater = $this->createMock(InAppPurchaseUpdater::class);
        $iapUpdater->expects(static::once())->method('update');

        $subscriber = new LicenseHostChangedSubscriber(
            new StaticSystemConfigService(),
            $this->createMock(Connection::class),
            $iapUpdater,
        );

        $event = new SystemConfigChangedEvent('core.store.shopSecret', 'secret', null);
        $subscriber->updateIapKey($event);
    }

    public function testRemoveIapInformationFromDomainOnlyActsOnStoreDomain(): void
    {
        $subscriber = new LicenseHostChangedSubscriber(
            new StaticSystemConfigService(),
            $this->createMock(Connection::class),
            $this->createMock(InAppPurchaseUpdater::class),
        );

        $event = new SystemConfigDomainLoadedEvent('some.domain.', ['core.store.iapKey' => 'key'], false, null);
        $subscriber->removeIapInformationFromDomain($event);

        static::assertSame(['core.store.iapKey' => 'key'], $event->getConfig());
    }

    public function testRemoveIapInformationCleansDomain(): void
    {
        $subscriber = new LicenseHostChangedSubscriber(
            new StaticSystemConfigService(),
            $this->createMock(Connection::class),
            $this->createMock(InAppPurchaseUpdater::class),
        );

        $event = new SystemConfigDomainLoadedEvent('core.store.', ['core.store.iapKey' => 'key'], false, null);
        $subscriber->removeIapInformationFromDomain($event);

        static::assertSame([], $event->getConfig());
    }
}
