<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ShopId\ShopIdDeletedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\Definition\BackendData;
use Shopware\Core\System\UsageData\Consent\BannerService;
use Shopware\Core\System\UsageData\Services\EntityDispatchService;
use Shopware\Core\System\UsageData\Subscriber\ShopIdChangedSubscriber;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(ShopIdChangedSubscriber::class)]
class ShopIdChangedSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame([
            ShopIdDeletedEvent::class => 'handleShopIdDeleted',
        ], ShopIdChangedSubscriber::getSubscribedEvents());
    }

    public function testResetConsentWhenShopIdIsDeleted(): void
    {
        $bannerService = $this->createMock(BannerService::class);
        $bannerService->expects($this->once())
            ->method('resetIsBannerHiddenForAllUsers');

        $entityDispatchService = $this->createMock(EntityDispatchService::class);
        $entityDispatchService->expects($this->once())
            ->method('resetLastRunDateForAllEntities');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                static::stringContains('DELETE FROM consent_state'),
                ['name' => BackendData::NAME, 'identifier' => 'system']
            );

        $shopIdChangedSubscriber = new ShopIdChangedSubscriber(
            $bannerService,
            $entityDispatchService,
            $connection
        );

        $shopIdChangedSubscriber->handleShopIdDeleted(new ShopIdDeletedEvent());
    }
}
