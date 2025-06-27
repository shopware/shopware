<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\InAppPurchases\Handler;

use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseUpdater;
use Shopware\Core\Framework\Store\InAppPurchase\Handler\InAppPurchaseUpdateHandler;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(InAppPurchaseUpdateHandler::class)]
class InAppPurchaseUpdateHandlerTest extends TestCase
{
    private InAppPurchaseUpdater&MockObject $iapUpdater;

    private LoggerInterface&MockObject $logger;

    private AbstractStoreRequestOptionsProvider&MockObject $storeRequestOptionsProvider;

    private InAppPurchaseUpdateHandler $iapUpdateHandler;

    protected function setUp(): void
    {
        $this->iapUpdater = $this->createMock(InAppPurchaseUpdater::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->storeRequestOptionsProvider = $this->createMock(AbstractStoreRequestOptionsProvider::class);

        $this->iapUpdateHandler = new InAppPurchaseUpdateHandler(
            $this->createMock(EntityRepository::class),
            $this->logger,
            $this->iapUpdater,
            $this->storeRequestOptionsProvider,
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
            ->method($this->anything());

        $this->storeRequestOptionsProvider
            ->expects($this->once())
            ->method('getAuthenticationHeader')
            ->willReturn(['auth-header' => 'token']);

        $this->iapUpdateHandler->run();
    }

    public function testWithoutAuthenticationHeaders(): void
    {
        $this->iapUpdater
            ->expects($this->never())
            ->method('update');

        $this->logger
            ->expects($this->never())
            ->method($this->anything());

        $this->storeRequestOptionsProvider
            ->expects($this->once())
            ->method('getAuthenticationHeader')
            ->willReturn([]);

        $this->iapUpdateHandler->run();
    }
}
