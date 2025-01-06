<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\InAppPurchases\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase\Handler\InAppPurchaseUpdateHandler;
use Shopware\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseUpdater;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(InAppPurchaseUpdateHandler::class)]
class InAppPurchaseSyncHandlerTest extends TestCase
{
    public function testRunWithActiveInAppPurchases(): void
    {
        $syncService = $this->createMock(InAppPurchaseUpdater::class);
        $syncService->expects(static::once())
            ->method('update')
            ->with(Context::createCLIContext());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::never())
            ->method('error');

        $handler = new InAppPurchaseUpdateHandler(
            $this->createMock(EntityRepository::class),
            $logger,
            $syncService
        );

        $handler->run();
    }
}
