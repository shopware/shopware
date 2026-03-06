<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\Scenario;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\CartCheckoutTool;
use Shopware\Core\Framework\Mcp\Tool\CartManageTool;
use Shopware\Core\Framework\Mcp\Tool\CheckoutMethodsTool;
use Shopware\Core\Framework\Mcp\Tool\ConsoleCommandTool;
use Shopware\Core\Framework\Mcp\Tool\CustomerLookupTool;
use Shopware\Core\Framework\Mcp\Tool\EntityReadTool;
use Shopware\Core\Framework\Mcp\Tool\EntitySchemaTool;
use Shopware\Core\Framework\Mcp\Tool\EntitySearchTool;
use Shopware\Core\Framework\Mcp\Tool\OrderSummaryTool;
use Shopware\Core\Framework\Mcp\Tool\ProductCreateTool;
use Shopware\Core\Framework\Mcp\Tool\RevenueReportTool;
use Shopware\Core\Framework\Mcp\Tool\StateMachineTransitionTool;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigReadTool;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigWriteTool;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('framework')]
abstract class McpScenarioTestCase extends TestCase
{
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    protected EntitySearchTool $entitySearchTool;

    protected EntitySchemaTool $entitySchemaTool;

    protected EntityReadTool $entityReadTool;

    protected SystemConfigReadTool $systemConfigReadTool;

    protected SystemConfigWriteTool $systemConfigWriteTool;

    protected ConsoleCommandTool $consoleCommandTool;

    protected StateMachineTransitionTool $stateMachineTransitionTool;

    protected OrderSummaryTool $orderSummaryTool;

    protected ProductCreateTool $productCreateTool;

    protected CustomerLookupTool $customerLookupTool;

    protected RevenueReportTool $revenueReportTool;

    protected CartManageTool $cartManageTool;

    protected CartCheckoutTool $cartCheckoutTool;

    protected CheckoutMethodsTool $checkoutMethodsTool;

    protected function setUp(): void
    {
        Feature::skipTestIfInActive('MCP_SERVER', $this);

        $container = static::getContainer();
        $registry = $container->get(DefinitionInstanceRegistry::class);

        /** @var RequestCriteriaBuilder $criteriaBuilder */
        $criteriaBuilder = $container->get(RequestCriteriaBuilder::class);

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createDefaultContext());

        /** @var JsonEntityEncoder $encoder */
        $encoder = $container->get(JsonEntityEncoder::class);

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        $this->entitySearchTool = new EntitySearchTool($registry, $criteriaBuilder, $contextProvider, $encoder);
        $this->entitySchemaTool = new EntitySchemaTool($registry);
        $this->entityReadTool = new EntityReadTool($registry, $criteriaBuilder, $contextProvider, $encoder);

        /** @var SystemConfigService $systemConfigService */
        $systemConfigService = $container->get(SystemConfigService::class);
        $this->systemConfigReadTool = new SystemConfigReadTool($systemConfigService);
        $this->systemConfigWriteTool = new SystemConfigWriteTool($systemConfigService);

        /** @var StateMachineRegistry $stateMachineRegistry */
        $stateMachineRegistry = $container->get(StateMachineRegistry::class);
        $this->stateMachineTransitionTool = new StateMachineTransitionTool($stateMachineRegistry, $contextProvider);

        /** @var list<string> $allowedCommands */
        $allowedCommands = $container->getParameter('shopware.mcp.allowed_console_commands');
        $this->consoleCommandTool = new ConsoleCommandTool(
            $container->get('kernel'),
            $allowedCommands,
        );

        $this->orderSummaryTool = new OrderSummaryTool($registry, $contextProvider);
        $this->customerLookupTool = new CustomerLookupTool($registry, $contextProvider);
        $this->productCreateTool = new ProductCreateTool($registry, $contextProvider, $connection);
        $this->revenueReportTool = new RevenueReportTool($registry, $contextProvider);

        /** @var SalesChannelContextService $salesChannelContextService */
        $salesChannelContextService = $container->get(SalesChannelContextService::class);

        /** @var CartService $cartService */
        $cartService = $container->get(CartService::class);
        $this->cartManageTool = new CartManageTool($salesChannelContextService, $cartService);
        $this->cartCheckoutTool = new CartCheckoutTool($salesChannelContextService, $cartService);

        /** @var PaymentMethodRoute $paymentMethodRoute */
        $paymentMethodRoute = $container->get(PaymentMethodRoute::class);

        /** @var ShippingMethodRoute $shippingMethodRoute */
        $shippingMethodRoute = $container->get(ShippingMethodRoute::class);
        $this->checkoutMethodsTool = new CheckoutMethodsTool($salesChannelContextService, $paymentMethodRoute, $shippingMethodRoute);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeToolOutput(string $json): array
    {
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertTrue($data['success'], 'Tool call failed: ' . ($data['error'] ?? 'unknown'));

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeToolError(string $json): array
    {
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertFalse($data['success']);

        return $data;
    }
}
