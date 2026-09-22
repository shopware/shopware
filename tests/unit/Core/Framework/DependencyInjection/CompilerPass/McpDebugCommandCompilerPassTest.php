<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\McpDebugCommandCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpDebugCommandCompilerPass::class)]
class McpDebugCommandCompilerPassTest extends TestCase
{
    public function testBundleCommandIsMovedOffDebugMcp(): void
    {
        $container = new ContainerBuilder();
        $container->register('mcp.debug_command')->addTag('console.command');

        (new McpDebugCommandCompilerPass())->process($container);

        static::assertSame(
            [['command' => 'debug:mcp:native']],
            $container->getDefinition('mcp.debug_command')->getTag('console.command'),
        );
    }

    /**
     * AddConsoleCommandPass reads the name from the first console.command tag, so a leftover
     * attribute-less tag would still claim "debug:mcp" via the class's own #[AsCommand].
     */
    public function testNoUnnamedConsoleCommandTagSurvives(): void
    {
        $container = new ContainerBuilder();
        $container->register('mcp.debug_command')->addTag('console.command');

        (new McpDebugCommandCompilerPass())->process($container);

        foreach ($container->getDefinition('mcp.debug_command')->getTag('console.command') as $attributes) {
            static::assertArrayHasKey('command', $attributes);
        }
    }

    public function testDoesNothingWithoutTheBundleCommand(): void
    {
        $container = new ContainerBuilder();

        (new McpDebugCommandCompilerPass())->process($container);

        static::assertFalse($container->hasDefinition('mcp.debug_command'));
    }
}
