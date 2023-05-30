<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\ApiOrderCartService;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCollector;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
class ApiOrderCartServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private SalesChannelContextPersister $contextPersister;

    private SalesChannelContext $salesChannelContext;

    private ApiOrderCartService $adminOrderCartService;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $eventDispatcher = new EventDispatcher();
        $this->contextPersister = new SalesChannelContextPersister($this->connection, $eventDispatcher, $this->getContainer()->get(CartPersister::class));
        $this->salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $this->adminOrderCartService = $this->getContainer()->get(ApiOrderCartService::class);
    }

    public function testAddPermission(): void
    {
        $this->adminOrderCartService->addPermission($this->salesChannelContext->getToken(), PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS, $this->salesChannelContext->getSalesChannelId());
        $payload = $this->contextPersister->load($this->salesChannelContext->getToken(), $this->salesChannelContext->getSalesChannelId());
        static::assertArrayHasKey(PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS, $payload[SalesChannelContextService::PERMISSIONS]);
        static::assertTrue($payload[SalesChannelContextService::PERMISSIONS][PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS]);
    }

    public function testAddMultiplePermissions(): void
    {
        $this->adminOrderCartService->addPermission($this->salesChannelContext->getToken(), PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS, $this->salesChannelContext->getSalesChannelId());
        $this->adminOrderCartService->addPermission($this->salesChannelContext->getToken(), PromotionCollector::SKIP_PROMOTION, $this->salesChannelContext->getSalesChannelId());
        $payload = $this->contextPersister->load($this->salesChannelContext->getToken(), $this->salesChannelContext->getSalesChannelId());

        static::assertArrayHasKey(SalesChannelContextService::PERMISSIONS, $payload);
        static::assertCount(2, $payload[SalesChannelContextService::PERMISSIONS]);
        static::assertArrayHasKey(PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS, $payload[SalesChannelContextService::PERMISSIONS]);
        static::assertTrue($payload[SalesChannelContextService::PERMISSIONS][PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS]);

        static::assertArrayHasKey(PromotionCollector::SKIP_PROMOTION, $payload[SalesChannelContextService::PERMISSIONS]);
        static::assertTrue($payload[SalesChannelContextService::PERMISSIONS][PromotionCollector::SKIP_PROMOTION]);
    }

    public function testDeletePermission(): void
    {
        $this->adminOrderCartService->addPermission($this->salesChannelContext->getToken(), PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS, $this->salesChannelContext->getSalesChannelId());
        $payload = $this->contextPersister->load($this->salesChannelContext->getToken(), $this->salesChannelContext->getSalesChannelId());
        static::assertArrayHasKey(PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS, $payload[SalesChannelContextService::PERMISSIONS]);
        static::assertTrue($payload[SalesChannelContextService::PERMISSIONS][PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS]);

        $this->adminOrderCartService->deletePermission($this->salesChannelContext->getToken(), PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS, $this->salesChannelContext->getSalesChannelId());
        $payload = $this->contextPersister->load($this->salesChannelContext->getToken(), $this->salesChannelContext->getSalesChannelId());
        static::assertArrayHasKey(PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS, $payload[SalesChannelContextService::PERMISSIONS]);
        static::assertFalse($payload[SalesChannelContextService::PERMISSIONS][PromotionCollector::SKIP_AUTOMATIC_PROMOTIONS]);
    }
}
