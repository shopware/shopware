<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Controller\McpServerController;
use Shopware\Core\Framework\Mcp\Loader\AppMcpToolExecutor;
use Shopware\Core\Framework\Mcp\Loader\AppMcpToolLoader;
use Shopware\Core\Framework\Mcp\Prompt\ShopwareContextPrompt;
use Shopware\Core\Framework\Mcp\Resource\BusinessEventsResource;
use Shopware\Core\Framework\Mcp\Resource\CurrencyListResource;
use Shopware\Core\Framework\Mcp\Resource\EntityListResource;
use Shopware\Core\Framework\Mcp\Resource\FlowActionsResource;
use Shopware\Core\Framework\Mcp\Resource\LanguageListResource;
use Shopware\Core\Framework\Mcp\Resource\SalesChannelListResource;
use Shopware\Core\Framework\Mcp\Resource\StateMachineResource;
use Shopware\Core\Framework\Mcp\Tool\ConsoleCommandTool;
use Shopware\Core\Framework\Mcp\Tool\CustomerLookupTool;
use Shopware\Core\Framework\Mcp\Tool\EntityDeleteTool;
use Shopware\Core\Framework\Mcp\Tool\EntityReadTool;
use Shopware\Core\Framework\Mcp\Tool\EntitySchemaTool;
use Shopware\Core\Framework\Mcp\Tool\EntitySearchTool;
use Shopware\Core\Framework\Mcp\Tool\EntityUpsertTool;
use Shopware\Core\Framework\Mcp\Tool\FlowCreateTool;
use Shopware\Core\Framework\Mcp\Tool\OrderSummaryTool;
use Shopware\Core\Framework\Mcp\Tool\ProductCreateTool;
use Shopware\Core\Framework\Mcp\Tool\RevenueReportTool;
use Shopware\Core\Framework\Mcp\Tool\StorefrontSearchTool;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigReadTool;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigWriteTool;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @internal
 */
#[CoversNothing]
#[Package('framework')]
class McpServiceConfigTest extends TestCase
{
    public function testMcpServicesAreRegistered(): void
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator());
        $loader->load(__DIR__ . '/../../../../../src/Core/Framework/DependencyInjection/mcp.php');

        $expectedServices = [
            McpServerController::class,
            EntitySchemaTool::class,
            EntitySearchTool::class,
            EntityReadTool::class,
            EntityUpsertTool::class,
            EntityDeleteTool::class,
            SystemConfigReadTool::class,
            SystemConfigWriteTool::class,
            ConsoleCommandTool::class,
            StorefrontSearchTool::class,
            OrderSummaryTool::class,
            CustomerLookupTool::class,
            ProductCreateTool::class,
            RevenueReportTool::class,
            FlowCreateTool::class,
            ShopwareContextPrompt::class,
            EntityListResource::class,
            BusinessEventsResource::class,
            FlowActionsResource::class,
            SalesChannelListResource::class,
            CurrencyListResource::class,
            LanguageListResource::class,
            StateMachineResource::class,
            AppMcpToolExecutor::class,
            AppMcpToolLoader::class,
        ];

        foreach ($expectedServices as $serviceId) {
            static::assertTrue($container->hasDefinition($serviceId), \sprintf('Service "%s" is not registered', $serviceId));
        }
    }

    public function testToolServicesAreTagged(): void
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator());
        $loader->load(__DIR__ . '/../../../../../src/Core/Framework/DependencyInjection/mcp.php');

        $toolServices = [
            EntitySchemaTool::class,
            EntitySearchTool::class,
            EntityReadTool::class,
            EntityUpsertTool::class,
            EntityDeleteTool::class,
            SystemConfigReadTool::class,
            SystemConfigWriteTool::class,
            ConsoleCommandTool::class,
            StorefrontSearchTool::class,
            OrderSummaryTool::class,
            CustomerLookupTool::class,
            ProductCreateTool::class,
            RevenueReportTool::class,
            FlowCreateTool::class,
        ];

        foreach ($toolServices as $serviceId) {
            $definition = $container->getDefinition($serviceId);
            static::assertTrue($definition->hasTag('mcp.tool'), \sprintf('Service "%s" is not tagged with mcp.tool', $serviceId));
            static::assertTrue($definition->hasTag('shopware.feature'), \sprintf('Service "%s" is not tagged with shopware.feature', $serviceId));
        }
    }

    public function testPromptServiceIsTagged(): void
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator());
        $loader->load(__DIR__ . '/../../../../../src/Core/Framework/DependencyInjection/mcp.php');

        $definition = $container->getDefinition(ShopwareContextPrompt::class);
        static::assertTrue($definition->hasTag('mcp.prompt'));
    }

    public function testResourceServicesAreTagged(): void
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator());
        $loader->load(__DIR__ . '/../../../../../src/Core/Framework/DependencyInjection/mcp.php');

        $resourceServices = [
            EntityListResource::class,
            BusinessEventsResource::class,
            FlowActionsResource::class,
            SalesChannelListResource::class,
            CurrencyListResource::class,
            LanguageListResource::class,
            StateMachineResource::class,
        ];

        foreach ($resourceServices as $serviceId) {
            $definition = $container->getDefinition($serviceId);
            static::assertTrue($definition->hasTag('mcp.resource'), \sprintf('Service "%s" is not tagged with mcp.resource', $serviceId));
            static::assertTrue($definition->hasTag('shopware.feature'), \sprintf('Service "%s" is not tagged with shopware.feature', $serviceId));
        }
    }

    public function testAppMcpToolLoaderIsTaggedAsMcpLoader(): void
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator());
        $loader->load(__DIR__ . '/../../../../../src/Core/Framework/DependencyInjection/mcp.php');

        $definition = $container->getDefinition(AppMcpToolLoader::class);
        static::assertTrue($definition->hasTag('mcp.loader'));
    }

    public function testControllerIsPublic(): void
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator());
        $loader->load(__DIR__ . '/../../../../../src/Core/Framework/DependencyInjection/mcp.php');

        $definition = $container->getDefinition(McpServerController::class);
        static::assertTrue($definition->isPublic());
    }
}
