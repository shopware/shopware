<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\InAppPurchases\Handler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
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
class InAppPurchaseUpdateHandlerTest extends TestCase
{
    private InAppPurchaseUpdater&MockObject $iapUpdater;

    private LoggerInterface&MockObject $logger;

    private InAppPurchaseUpdateHandler $iapUpdateHandler;

    protected function setUp(): void
    {
        $this->iapUpdater = $this->createMock(InAppPurchaseUpdater::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->iapUpdateHandler = new InAppPurchaseUpdateHandler(
            $this->createMock(EntityRepository::class),
            $this->logger,
            $this->iapUpdater,
        );
    }

    public function testRunWithActiveInAppPurchases(): void
    {
        $this->iapUpdater
            ->expects($this->once())
            ->method('update')
            ->with(Context::createCLIContext());

        $this->logger
            ->expects($this->never())
            ->method(static::anything());

        $this->iapUpdateHandler->run();
    }
}
