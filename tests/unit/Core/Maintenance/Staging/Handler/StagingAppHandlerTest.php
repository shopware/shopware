<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\Staging\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\ShopIdChangeResolver\ReinstallAppsStrategy;
use Shopware\Core\Framework\App\ShopIdChangeResolver\Resolver;
use Shopware\Core\Framework\App\ShopIdChangeResolver\ShopIdChangeStrategy;
use Shopware\Core\Framework\App\ShopIdChangeResolver\UninstallAppsStrategy;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Shopware\Core\Maintenance\Staging\Handler\StagingAppHandler;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StagingAppHandler::class)]
class StagingAppHandlerTest extends TestCase
{
    private ShopIdChangeStrategy&MockObject $uninstallApps;

    private ShopIdChangeStrategy&MockObject $reinstallApps;

    private StagingAppHandler $handler;

    protected function setUp(): void
    {
        $this->uninstallApps = $this->createMock(ShopIdChangeStrategy::class);
        $this->uninstallApps->method('getName')->willReturn(UninstallAppsStrategy::STRATEGY_NAME);

        $this->reinstallApps = $this->createMock(ShopIdChangeStrategy::class);
        $this->reinstallApps->method('getName')->willReturn(ReinstallAppsStrategy::STRATEGY_NAME);

        $this->handler = new StagingAppHandler(new Resolver([$this->uninstallApps, $this->reinstallApps]));
    }

    public function testAppsAreUninstalledByDefault(): void
    {
        $this->uninstallApps->expects($this->once())->method('resolve');
        $this->reinstallApps->expects($this->never())->method('resolve');

        $event = new SetupStagingEvent(Context::createDefaultContext(), static::createStub(SymfonyStyle::class));
        ($this->handler)($event);

        static::assertFalse($event->canceled);
    }

    public function testAppsAreReinstalledWhenRequested(): void
    {
        $this->uninstallApps->expects($this->never())->method('resolve');
        $this->reinstallApps->expects($this->once())->method('resolve');

        $event = new SetupStagingEvent(Context::createDefaultContext(), static::createStub(SymfonyStyle::class), reinstallApps: true);
        ($this->handler)($event);

        static::assertFalse($event->canceled);
    }

    public function testAFailedReinstallCancelsTheSetup(): void
    {
        $this->uninstallApps->expects($this->never())->method('resolve');
        $this->reinstallApps->expects($this->once())->method('resolve')->willThrowException(AppException::reinstallAppsFailed(['TestApp']));

        $event = new SetupStagingEvent(Context::createDefaultContext(), static::createStub(SymfonyStyle::class), reinstallApps: true);
        ($this->handler)($event);

        static::assertTrue($event->canceled);
    }
}
