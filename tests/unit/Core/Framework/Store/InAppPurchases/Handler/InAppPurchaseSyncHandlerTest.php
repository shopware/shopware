<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\InAppPurchases\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase\Handler\InAppPurchaseSyncHandler;
use Shopware\Core\Framework\Store\InAppPurchase\Services\InAppPurchasesSyncService;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(InAppPurchaseSyncHandler::class)]
class InAppPurchaseSyncHandlerTest extends TestCase
{
    public function testRunWithActiveInAppPurchases(): void
    {
        $syncService = $this->createMock(InAppPurchasesSyncService::class);
        $syncService->expects(static::once())
            ->method('updateActiveInAppPurchases')
            ->with(Context::createCLIContext());

        $syncService->expects(static::once())
            ->method('disableExpiredInAppPurchases');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::never())
            ->method('error');

        $handler = new InAppPurchaseSyncHandler(
            $this->createMock(EntityRepository::class),
            $logger,
            $syncService
        );

        $handler->run();
    }

    public function testRunWithException(): void
    {
        $exception = new \Exception('Test');

        $syncService = $this->createMock(InAppPurchasesSyncService::class);
        $syncService->expects(static::once())
            ->method('updateActiveInAppPurchases')
            ->with(Context::createCLIContext())
            ->willThrowException($exception);

        $syncService->expects(static::once())
            ->method('disableExpiredInAppPurchases');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())
            ->method('error')
            ->with('Test', ['exception' => $exception]);

        $handler = new InAppPurchaseSyncHandler(
            $this->createMock(EntityRepository::class),
            $logger,
            $syncService
        );

        $handler->run();
    }
}
