<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Subscriber\LicenseHostChangedSubscriber;
use Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigChangedEvent;
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
            'core.store.iapKey' => 'iap-key',
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('executeStatement')->with('UPDATE user SET store_token = NULL');

        $subscriber = new LicenseHostChangedSubscriber($config, $connection);

        $event = new BeforeSystemConfigChangedEvent('core.store.licenseHost', 'otherhost', null);
        $subscriber->onLicenseHostChanged($event);

        static::assertNull($config->get('core.store.shopSecret'));
        static::assertNull($config->get('core.store.iapKey'));
    }
}
