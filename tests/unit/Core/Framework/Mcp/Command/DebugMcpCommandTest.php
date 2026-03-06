<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Command;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Command\DebugMcpCommand;
use Shopware\Core\Framework\Mcp\Tool\EntitySchemaTool;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DebugMcpCommand::class)]
class DebugMcpCommandTest extends TestCase
{
    public function testOutputsToolsSection(): void
    {
        $tool = $this->createMock(EntitySchemaTool::class);

        $command = new DebugMcpCommand([$tool], [], []);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $output = $tester->getDisplay();

        static::assertStringContainsString('Tools', $output);
        static::assertStringContainsString('Prompts', $output);
        static::assertStringContainsString('Resources', $output);
    }

    public function testOutputsEmptySections(): void
    {
        $command = new DebugMcpCommand([], [], []);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $output = $tester->getDisplay();

        static::assertStringContainsString('No capabilities registered', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testOutputsTableRowsForItemsWithMcpAttributes(): void
    {
        $toolWithAttr = new #[McpTool(name: 'test-tool', description: 'A test tool')] class {
        };

        $command = new DebugMcpCommand([$toolWithAttr], [], []);
        $tester = new CommandTester($command);

        $tester->execute([]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('test-tool', $output);
        static::assertStringContainsString('A test tool', $output);
        static::assertStringContainsString('Name', $output);
        static::assertStringContainsString('Description', $output);
        static::assertStringContainsString('Class', $output);
    }
}
