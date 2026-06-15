<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\McpServerBuilderCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpServerBuilderCompilerPass::class)]
class McpServerBuilderCompilerPassTest extends TestCase
{
    public function testDiscoveryCacheIsWiredWhenServiceExists(): void
    {
        $container = $this->createContainer();
        $container->register('shopware.mcp.discovery_cache');

        $builderDef = $container->getDefinition('mcp.server.builder');
        $builderDef->addMethodCall('setDiscovery', [
            new Reference('mcp.discovery.reflection'),
            [],
            [],
        ]);

        $pass = new McpServerBuilderCompilerPass();
        $pass->process($container);

        $calls = $builderDef->getMethodCalls();
        $setDiscoveryCalls = array_filter($calls, fn ($c) => $c[0] === 'setDiscovery');

        static::assertNotEmpty($setDiscoveryCalls);

        $lastCall = end($setDiscoveryCalls);
        static::assertInstanceOf(Reference::class, $lastCall[1][3]);
        static::assertSame('shopware.mcp.discovery_cache', (string) $lastCall[1][3]);
    }

    public function testDiscoveryCacheSkipsNonSetDiscoveryMethodCalls(): void
    {
        $container = $this->createContainer();
        $container->register('shopware.mcp.discovery_cache');

        $builderDef = $container->getDefinition('mcp.server.builder');
        $builderDef->addMethodCall('setSomethingElse', ['arg1']);
        $builderDef->addMethodCall('setDiscovery', [
            new Reference('mcp.discovery.reflection'),
            [],
            [],
        ]);

        $pass = new McpServerBuilderCompilerPass();
        $pass->process($container);

        $calls = $builderDef->getMethodCalls();
        $setDiscoveryCalls = array_filter($calls, fn ($c) => $c[0] === 'setDiscovery');

        static::assertNotEmpty($setDiscoveryCalls);

        $lastCall = end($setDiscoveryCalls);
        static::assertInstanceOf(Reference::class, $lastCall[1][3]);
        static::assertSame('shopware.mcp.discovery_cache', (string) $lastCall[1][3]);
    }

    public function testDiscoveryCacheSkippedWhenNoCacheService(): void
    {
        $container = $this->createContainer();

        $builderDef = $container->getDefinition('mcp.server.builder');
        $builderDef->addMethodCall('setDiscovery', [
            new Reference('mcp.discovery.reflection'),
            [],
            [],
        ]);

        $pass = new McpServerBuilderCompilerPass();
        $pass->process($container);

        $calls = $builderDef->getMethodCalls();
        $setDiscoveryCalls = array_filter($calls, fn ($c) => $c[0] === 'setDiscovery');

        foreach ($setDiscoveryCalls as $call) {
            static::assertArrayNotHasKey(3, $call[1]);
        }
    }

    public function testPluginToolsAreRegisteredWithBuilder(): void
    {
        $container = $this->createContainer();

        $def = new Definition(McpBuilderTestNamespacedTool::class);
        $def->addTag('shopware.mcp.tool');
        $container->setDefinition(McpBuilderTestNamespacedTool::class, $def);

        $pass = new McpServerBuilderCompilerPass();
        $pass->process($container);

        $calls = $container->getDefinition('mcp.server.builder')->getMethodCalls();
        $addToolCalls = array_filter($calls, fn ($c) => $c[0] === 'addTool');

        static::assertNotEmpty($addToolCalls);

        $args = array_values($addToolCalls)[0][1];
        static::assertSame(McpBuilderTestNamespacedTool::class, $args[0]);
        static::assertSame('my-builder-namespaced-tool', $args[1]);
        static::assertNull($args[2]);
        static::assertSame('test namespaced tool', $args[3]);
    }

    public function testPluginPromptsAreRegisteredWithBuilder(): void
    {
        $container = $this->createContainer();

        $def = new Definition(McpBuilderTestPrompt::class);
        $def->addTag('shopware.mcp.prompt');
        $container->setDefinition(McpBuilderTestPrompt::class, $def);

        $pass = new McpServerBuilderCompilerPass();
        $pass->process($container);

        $calls = $container->getDefinition('mcp.server.builder')->getMethodCalls();
        $addPromptCalls = array_filter($calls, fn ($c) => $c[0] === 'addPrompt');

        static::assertNotEmpty($addPromptCalls);

        $args = array_values($addPromptCalls)[0][1];
        static::assertSame(McpBuilderTestPrompt::class, $args[0]);
        static::assertSame('my-builder-prompt', $args[1]);
        static::assertNull($args[2]);
        static::assertSame('test plugin prompt', $args[3]);
    }

    public function testPluginResourcesAreRegisteredWithBuilder(): void
    {
        $container = $this->createContainer();

        $def = new Definition(McpBuilderTestResource::class);
        $def->addTag('shopware.mcp.resource');
        $container->setDefinition(McpBuilderTestResource::class, $def);

        $pass = new McpServerBuilderCompilerPass();
        $pass->process($container);

        $calls = $container->getDefinition('mcp.server.builder')->getMethodCalls();
        $addResourceCalls = array_filter($calls, fn ($c) => $c[0] === 'addResource');

        static::assertNotEmpty($addResourceCalls);

        $args = array_values($addResourceCalls)[0][1];
        static::assertSame(McpBuilderTestResource::class, $args[0]);
        static::assertSame('plugin://builder-resource', $args[1]);
        static::assertSame('my-builder-resource', $args[2]);
        static::assertSame('test plugin resource', $args[3]);
        static::assertNull($args[4]);
    }

    public function testPluginResourceWithMimeTypeIsRegisteredWithBuilder(): void
    {
        $container = $this->createContainer();

        $def = new Definition(McpBuilderTestResourceWithMimeType::class);
        $def->addTag('shopware.mcp.resource');
        $container->setDefinition(McpBuilderTestResourceWithMimeType::class, $def);

        $pass = new McpServerBuilderCompilerPass();
        $pass->process($container);

        $calls = $container->getDefinition('mcp.server.builder')->getMethodCalls();
        $addResourceCalls = array_filter($calls, fn ($c) => $c[0] === 'addResource');

        static::assertNotEmpty($addResourceCalls);

        $args = array_values($addResourceCalls)[0][1];
        static::assertSame('application/json', $args[4]);
    }

    public function testMethodLevelMcpToolAttributeIsDetected(): void
    {
        $container = $this->createContainer();

        $def = new Definition(McpBuilderTestMethodLevelTool::class);
        $def->addTag('shopware.mcp.tool');
        $container->setDefinition(McpBuilderTestMethodLevelTool::class, $def);

        $pass = new McpServerBuilderCompilerPass();
        $pass->process($container);

        $calls = $container->getDefinition('mcp.server.builder')->getMethodCalls();
        $addToolCalls = array_filter($calls, fn ($c) => $c[0] === 'addTool');

        static::assertNotEmpty($addToolCalls);

        $args = array_values($addToolCalls)[0][1];
        static::assertSame('my-builder-method-level-tool', $args[1]);
        static::assertNull($args[2]);
        static::assertSame('method-level description', $args[3]);
    }

    public function testMethodLevelMcpPromptAttributeIsDetected(): void
    {
        $container = $this->createContainer();

        $def = new Definition(McpBuilderTestMethodLevelPrompt::class);
        $def->addTag('shopware.mcp.prompt');
        $container->setDefinition(McpBuilderTestMethodLevelPrompt::class, $def);

        $pass = new McpServerBuilderCompilerPass();
        $pass->process($container);

        $calls = $container->getDefinition('mcp.server.builder')->getMethodCalls();
        $addPromptCalls = array_filter($calls, fn ($c) => $c[0] === 'addPrompt');

        static::assertNotEmpty($addPromptCalls);

        $args = array_values($addPromptCalls)[0][1];
        static::assertSame('my-builder-method-prompt', $args[1]);
        static::assertNull($args[2]);
        static::assertSame('method-level prompt description', $args[3]);
    }

    public function testMethodLevelMcpResourceAttributeIsDetected(): void
    {
        $container = $this->createContainer();

        $def = new Definition(McpBuilderTestMethodLevelResource::class);
        $def->addTag('shopware.mcp.resource');
        $container->setDefinition(McpBuilderTestMethodLevelResource::class, $def);

        $pass = new McpServerBuilderCompilerPass();
        $pass->process($container);

        $calls = $container->getDefinition('mcp.server.builder')->getMethodCalls();
        $addResourceCalls = array_filter($calls, fn ($c) => $c[0] === 'addResource');

        static::assertNotEmpty($addResourceCalls);

        $args = array_values($addResourceCalls)[0][1];
        static::assertSame('plugin://builder-method-resource', $args[1]);
        static::assertSame('builder-method-resource', $args[2]);
        static::assertSame('method-level resource description', $args[3]);
    }

    public function testPluginToolTitleIsPassedToBuilder(): void
    {
        $container = $this->createContainer();

        $def = new Definition(McpBuilderTestToolWithTitle::class);
        $def->addTag('shopware.mcp.tool');
        $container->setDefinition(McpBuilderTestToolWithTitle::class, $def);

        $pass = new McpServerBuilderCompilerPass();
        $pass->process($container);

        $calls = $container->getDefinition('mcp.server.builder')->getMethodCalls();
        $args = array_values(array_filter($calls, fn ($c) => $c[0] === 'addTool'))[0][1];

        static::assertSame('tool-with-title', $args[1]);
        static::assertSame('Human-Readable Tool', $args[2]);
        static::assertSame('Performs actions', $args[3]);
    }

    public function testPluginPromptTitleIsPassedToBuilder(): void
    {
        $container = $this->createContainer();

        $def = new Definition(McpBuilderTestPromptWithTitle::class);
        $def->addTag('shopware.mcp.prompt');
        $container->setDefinition(McpBuilderTestPromptWithTitle::class, $def);

        $pass = new McpServerBuilderCompilerPass();
        $pass->process($container);

        $calls = $container->getDefinition('mcp.server.builder')->getMethodCalls();
        $args = array_values(array_filter($calls, fn ($c) => $c[0] === 'addPrompt'))[0][1];

        static::assertSame('prompt-with-title', $args[1]);
        static::assertSame('Human-Readable Prompt', $args[2]);
        static::assertSame('Sets context', $args[3]);
    }

    public function testSkipsWhenNoMcpServerBuilder(): void
    {
        $container = new ContainerBuilder();

        $pass = new McpServerBuilderCompilerPass();
        $pass->process($container);

        static::assertFalse($container->hasDefinition('mcp.server.builder'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('mcp.server.builder');

        return $container;
    }
}

/**
 * @internal
 */
#[McpTool(name: 'my-builder-namespaced-tool', description: 'test namespaced tool')]
class McpBuilderTestNamespacedTool extends McpToolResponse
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
#[McpPrompt(name: 'my-builder-prompt', description: 'test plugin prompt')]
class McpBuilderTestPrompt
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
#[McpResource(uri: 'plugin://builder-resource', name: 'my-builder-resource', description: 'test plugin resource')]
class McpBuilderTestResource
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
#[McpResource(uri: 'plugin://builder-mime-resource', name: 'mime-resource', description: 'mime resource', mimeType: 'application/json')]
class McpBuilderTestResourceWithMimeType
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
class McpBuilderTestMethodLevelTool extends McpToolResponse
{
    #[McpTool(name: 'my-builder-method-level-tool', description: 'method-level description')]
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
class McpBuilderTestMethodLevelPrompt
{
    #[McpPrompt(name: 'my-builder-method-prompt', description: 'method-level prompt description')]
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
class McpBuilderTestMethodLevelResource
{
    #[McpResource(uri: 'plugin://builder-method-resource', name: 'builder-method-resource', description: 'method-level resource description')]
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
#[McpTool(name: 'tool-with-title', title: 'Human-Readable Tool', description: 'Performs actions')]
class McpBuilderTestToolWithTitle extends McpToolResponse
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
#[McpPrompt(name: 'prompt-with-title', title: 'Human-Readable Prompt', description: 'Sets context')]
class McpBuilderTestPromptWithTitle
{
    public function __invoke(): string
    {
        return '';
    }
}
