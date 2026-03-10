<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\BestsellerReportTool;
use Shopware\Core\Framework\Mcp\Tool\CartCheckoutTool;
use Shopware\Core\Framework\Mcp\Tool\CartManageTool;
use Shopware\Core\Framework\Mcp\Tool\CheckoutMethodsTool;
use Shopware\Core\Framework\Mcp\Tool\CustomerLookupTool;
use Shopware\Core\Framework\Mcp\Tool\EntityDeleteTool;
use Shopware\Core\Framework\Mcp\Tool\EntityReadTool;
use Shopware\Core\Framework\Mcp\Tool\EntitySearchTool;
use Shopware\Core\Framework\Mcp\Tool\EntityUpsertTool;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Mcp\Tool\OrderCancelTool;
use Shopware\Core\Framework\Mcp\Tool\OrderSummaryTool;
use Shopware\Core\Framework\Mcp\Tool\ProductCreateTool;
use Shopware\Core\Framework\Mcp\Tool\RevenueReportTool;
use Shopware\Core\Framework\Mcp\Tool\StateMachineTransitionTool;
use Shopware\Core\Framework\Mcp\Tool\StorefrontSearchTool;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigReadTool;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigWriteTool;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolResponse::class)]
class AclEnforcementTest extends TestCase
{
    public function testEntitySearchToolDenied(): void
    {
        $tool = new EntitySearchTool(
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(RequestCriteriaBuilder::class),
            $this->createDeniedContextProvider(),
            $this->createMock(JsonEntityEncoder::class),
        );

        $this->assertAclDenied(($tool)('product'), 'product:read');
    }

    public function testEntityReadToolDenied(): void
    {
        $tool = new EntityReadTool(
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(RequestCriteriaBuilder::class),
            $this->createDeniedContextProvider(),
            $this->createMock(JsonEntityEncoder::class),
        );

        $this->assertAclDenied(($tool)('product', 'some-id'), 'product:read');
    }

    public function testEntityDeleteToolDenied(): void
    {
        $tool = new EntityDeleteTool(
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createDeniedContextProvider(),
            $this->createMock(Connection::class),
        );

        $this->assertAclDenied(($tool)('product', 'some-id'), 'product:delete');
    }

    public function testEntityUpsertToolDenied(): void
    {
        $tool = new EntityUpsertTool(
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createDeniedContextProvider(),
            $this->createMock(Connection::class),
        );

        $this->assertAclDenied(($tool)('product', '{"name":"test"}'), 'product:create');
    }

    public function testSystemConfigReadToolDenied(): void
    {
        $tool = new SystemConfigReadTool(
            $this->createMock(SystemConfigService::class),
            $this->createDeniedContextProvider(),
        );

        $this->assertAclDenied(($tool)('core.listing'), 'system_config:read');
    }

    public function testSystemConfigWriteToolDenied(): void
    {
        $tool = new SystemConfigWriteTool(
            $this->createMock(SystemConfigService::class),
            $this->createDeniedContextProvider(),
        );

        $this->assertAclDenied(($tool)('core.test', '"value"'), 'system_config:update');
    }

    public function testOrderSummaryToolDenied(): void
    {
        $tool = new OrderSummaryTool(
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createDeniedContextProvider(),
        );

        $this->assertAclDenied(($tool)('10001'), 'order:read');
    }

    public function testOrderCancelToolDenied(): void
    {
        $tool = new OrderCancelTool(
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createDeniedContextProvider(),
            $this->createMock(StateMachineRegistry::class),
        );

        $this->assertAclDenied(($tool)('10001'), 'order:read');
    }

    public function testCustomerLookupToolDenied(): void
    {
        $tool = new CustomerLookupTool(
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createDeniedContextProvider(),
        );

        $this->assertAclDenied(($tool)('test@example.com'), 'customer:read');
    }

    public function testProductCreateToolDenied(): void
    {
        $tool = new ProductCreateTool(
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createDeniedContextProvider(),
        );

        $this->assertAclDenied(($tool)('Test', 'SW-001', 19.99), 'product:create');
    }

    public function testBestsellerReportToolDenied(): void
    {
        $tool = new BestsellerReportTool(
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createDeniedContextProvider(),
        );

        $this->assertAclDenied(($tool)('2025-01-01', '2025-12-31'), 'order:read');
    }

    public function testRevenueReportToolDenied(): void
    {
        $tool = new RevenueReportTool(
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createDeniedContextProvider(),
        );

        $this->assertAclDenied(($tool)('2025-01-01', '2025-12-31'), 'order:read');
    }

    public function testStateMachineTransitionToolDenied(): void
    {
        $tool = new StateMachineTransitionTool(
            $this->createMock(StateMachineRegistry::class),
            $this->createDeniedContextProvider(),
        );

        $this->assertAclDenied(($tool)('order', 'some-id', 'cancel'), 'order:read');
    }

    public function testCartManageToolDenied(): void
    {
        $tool = new CartManageTool(
            $this->createMock(SalesChannelContextServiceInterface::class),
            $this->createMock(CartService::class),
            $this->createDeniedContextProvider(),
        );

        $this->assertAclDenied(($tool)('sc-1', 'create'), 'sales_channel:read');
    }

    public function testCartCheckoutToolDenied(): void
    {
        $tool = new CartCheckoutTool(
            $this->createMock(SalesChannelContextServiceInterface::class),
            $this->createMock(CartService::class),
            $this->createDeniedContextProvider(),
        );

        $this->assertAclDenied(($tool)('sc-1', 'token', 'cust-1'), 'sales_channel:read');
    }

    public function testCheckoutMethodsToolDenied(): void
    {
        $tool = new CheckoutMethodsTool(
            $this->createMock(SalesChannelContextServiceInterface::class),
            $this->createMock(AbstractPaymentMethodRoute::class),
            $this->createMock(AbstractShippingMethodRoute::class),
            $this->createDeniedContextProvider(),
        );

        $this->assertAclDenied(($tool)('sc-1'), 'sales_channel:read');
    }

    public function testStorefrontSearchToolDenied(): void
    {
        $tool = new StorefrontSearchTool(
            $this->createMock(SalesChannelContextServiceInterface::class),
            $this->createMock(SalesChannelRepository::class),
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(RequestCriteriaBuilder::class),
            $this->createMock(JsonEntityEncoder::class),
            $this->createDeniedContextProvider(),
            $this->createMock(Connection::class),
        );

        $this->assertAclDenied(($tool)('sc-1'), 'sales_channel:read');
    }

    private function createDeniedContextProvider(): McpContextProvider
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions([]);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $provider = $this->createMock(McpContextProvider::class);
        $provider->method('getContext')->willReturn($context);

        return $provider;
    }

    private function assertAclDenied(string $output, string $expectedPrivilege): void
    {
        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertStringContainsString('Missing privilege', $data['error']);
        static::assertStringContainsString($expectedPrivilege, $data['error']);
    }
}
