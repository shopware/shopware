<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlSynchronizer;
use Shopware\Storefront\Framework\Seo\App\Message\AppSeoUrlSyncHandler;
use Shopware\Storefront\Framework\Seo\App\Message\AppSeoUrlSyncMessage;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AppSeoUrlSyncHandler::class)]
class AppSeoUrlSyncHandlerTest extends TestCase
{
    private const APP_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testTheStaticRoutesAreSynchronisedWithoutRegeneratingTheEntityRoutes(): void
    {
        $synchronizer = $this->createMock(AppSeoUrlSynchronizer::class);
        $synchronizer->expects($this->once())->method('syncStaticRoutes')->with(self::APP_ID);
        $synchronizer->expects($this->never())->method('regenerateEntityRoutes');

        (new AppSeoUrlSyncHandler($synchronizer))(new AppSeoUrlSyncMessage(self::APP_ID));
    }

    public function testAFullSyncAlsoRegeneratesTheEntityRoutes(): void
    {
        $synchronizer = $this->createMock(AppSeoUrlSynchronizer::class);
        $synchronizer->expects($this->once())->method('syncStaticRoutes')->with(self::APP_ID);
        $synchronizer->expects($this->once())->method('regenerateEntityRoutes')->with(self::APP_ID);

        (new AppSeoUrlSyncHandler($synchronizer))(new AppSeoUrlSyncMessage(self::APP_ID, true));
    }

    public function testAMessageWithoutAnAppSynchronisesAllApps(): void
    {
        $synchronizer = $this->createMock(AppSeoUrlSynchronizer::class);
        $synchronizer->expects($this->once())->method('syncStaticRoutes')->with(null);

        (new AppSeoUrlSyncHandler($synchronizer))(new AppSeoUrlSyncMessage());
    }
}
