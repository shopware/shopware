<?php declare(strict_types=1);

namespace Shopware\Administration\Test\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Service\AdminOrderCartService;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCollector;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;

class AdminOrderCartServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var SalesChannelContextPersister
     */
    private $contextPersister;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @var AdminOrderCartService
     */
    private $adminOrderCartService;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $eventDispatcher = new EventDispatcher();
        $this->contextPersister = new SalesChannelContextPersister($this->connection, $eventDispatcher);
        $this->salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $this->adminOrderCartService = $this->getContainer()->get(AdminOrderCartService::class);
    }

    public function testAddPermission(): void
    {
        $this->adminOrderCartService->addPermission($this->salesChannelContext->getToken(), PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS);
        $payload = $this->contextPersister->load($this->salesChannelContext->getToken());
        static::assertArrayHasKey(PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS, $payload[SalesChannelContextService::PERMISSIONS]);
        static::assertTrue($payload[SalesChannelContextService::PERMISSIONS][PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS]);
    }

    public function testAddMultiplePermissions(): void
    {
        $this->adminOrderCartService->addPermission($this->salesChannelContext->getToken(), PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS);
        $this->adminOrderCartService->addPermission($this->salesChannelContext->getToken(), PromotionCollector::SKIP_PROMOTION);
        $payload = $this->contextPersister->load($this->salesChannelContext->getToken());

        static::assertArrayHasKey(SalesChannelContextService::PERMISSIONS, $payload);
        static::assertCount(2, $payload[SalesChannelContextService::PERMISSIONS]);
        static::assertArrayHasKey(PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS, $payload[SalesChannelContextService::PERMISSIONS]);
        static::assertTrue($payload[SalesChannelContextService::PERMISSIONS][PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS]);

        static::assertArrayHasKey(PromotionCollector::SKIP_PROMOTION, $payload[SalesChannelContextService::PERMISSIONS]);
        static::assertTrue($payload[SalesChannelContextService::PERMISSIONS][PromotionCollector::SKIP_PROMOTION]);
    }

    public function testDeletePermission(): void
    {
        $this->adminOrderCartService->addPermission($this->salesChannelContext->getToken(), PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS);
        $payload = $this->contextPersister->load($this->salesChannelContext->getToken());
        static::assertArrayHasKey(PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS, $payload[SalesChannelContextService::PERMISSIONS]);
        static::assertTrue($payload[SalesChannelContextService::PERMISSIONS][PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS]);

        $this->adminOrderCartService->deletePermission($this->salesChannelContext->getToken(), PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS);
        $payload = $this->contextPersister->load($this->salesChannelContext->getToken());
        static::assertArrayHasKey(PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS, $payload[SalesChannelContextService::PERMISSIONS]);
        static::assertFalse($payload[SalesChannelContextService::PERMISSIONS][PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS]);
    }
}
