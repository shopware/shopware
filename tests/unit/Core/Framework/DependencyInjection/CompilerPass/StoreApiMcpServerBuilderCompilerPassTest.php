<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\StoreApiMcpServerBuilderCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoreApiMcpServerBuilderCompilerPass::class)]
class StoreApiMcpServerBuilderCompilerPassTest extends TestCase
{
    public function testStoreApiToolsAreRegisteredWithStoreApiBuilder(): void
    {
        $container = $this->createContainer();

        $definition = new Definition(StoreApiMcpBuilderTestTool::class);
        $definition->addTag('shopware.store_api_mcp.tool');
        $container->setDefinition(StoreApiMcpBuilderTestTool::class, $definition);

        $pass = new StoreApiMcpServerBuilderCompilerPass();
        $pass->process($container);

        $calls = $container->getDefinition('mcp.store_api.server.builder')->getMethodCalls();
        $addToolCalls = array_filter($calls, fn ($call) => $call[0] === 'addTool');

        static::assertNotEmpty($addToolCalls);

        $args = array_values($addToolCalls)[0][1];
        static::assertSame(StoreApiMcpBuilderTestTool::class, $args[0]);
        static::assertSame('store-api-test-tool', $args[1]);
        static::assertSame('Store API Test Tool', $args[2]);
        static::assertSame('test store api tool', $args[3]);
    }

    public function testStoreApiPromptsAndResourcesAreRegisteredWithStoreApiBuilder(): void
    {
        $container = $this->createContainer();

        $promptDefinition = new Definition(StoreApiMcpBuilderTestPrompt::class);
        $promptDefinition->addTag('shopware.store_api_mcp.prompt');
        $container->setDefinition(StoreApiMcpBuilderTestPrompt::class, $promptDefinition);

        $resourceDefinition = new Definition(StoreApiMcpBuilderTestResource::class);
        $resourceDefinition->addTag('shopware.store_api_mcp.resource');
        $container->setDefinition(StoreApiMcpBuilderTestResource::class, $resourceDefinition);

        $pass = new StoreApiMcpServerBuilderCompilerPass();
        $pass->process($container);

        $calls = $container->getDefinition('mcp.store_api.server.builder')->getMethodCalls();

        $addPromptCalls = array_values(array_filter($calls, fn ($call) => $call[0] === 'addPrompt'));
        static::assertNotEmpty($addPromptCalls);
        static::assertSame('store-api-test-prompt', $addPromptCalls[0][1][1]);

        $addResourceCalls = array_values(array_filter($calls, fn ($call) => $call[0] === 'addResource'));
        static::assertNotEmpty($addResourceCalls);
        static::assertSame('store-api://test-resource', $addResourceCalls[0][1][1]);
    }

    public function testAdminBuilderIsNotMutated(): void
    {
        $container = $this->createContainer();
        $container->register('mcp.server.builder');

        $definition = new Definition(StoreApiMcpBuilderTestTool::class);
        $definition->addTag('shopware.store_api_mcp.tool');
        $container->setDefinition(StoreApiMcpBuilderTestTool::class, $definition);

        $pass = new StoreApiMcpServerBuilderCompilerPass();
        $pass->process($container);

        static::assertSame([], $container->getDefinition('mcp.server.builder')->getMethodCalls());
    }

    public function testNoServiceLocatorWiredWhenNoTaggedServices(): void
    {
        $container = $this->createContainer();

        $pass = new StoreApiMcpServerBuilderCompilerPass();
        $pass->process($container);

        $calls = $container->getDefinition('mcp.store_api.server.builder')->getMethodCalls();
        $setContainerCalls = array_filter($calls, fn ($call) => $call[0] === 'setContainer');
        static::assertEmpty($setContainerCalls);
    }

    public function testSkipsWhenNoStoreApiBuilder(): void
    {
        $container = new ContainerBuilder();

        $pass = new StoreApiMcpServerBuilderCompilerPass();
        $pass->process($container);

        static::assertFalse($container->hasDefinition('mcp.store_api.server.builder'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('mcp.store_api.server.builder');

        return $container;
    }
}

/**
 * @internal
 */
#[McpTool(name: 'store-api-test-tool', title: 'Store API Test Tool', description: 'test store api tool')]
class StoreApiMcpBuilderTestTool extends McpToolResponse
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
#[McpPrompt(name: 'store-api-test-prompt', description: 'test store api prompt')]
class StoreApiMcpBuilderTestPrompt
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
#[McpResource(uri: 'store-api://test-resource', name: 'store-api-test-resource', description: 'test store api resource')]
class StoreApiMcpBuilderTestResource
{
    public function __invoke(): string
    {
        return '';
    }
}
