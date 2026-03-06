<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\Scenario;

use PHPUnit\Framework\Attributes\CoversClass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\ConsoleCommandTool;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigReadTool;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigWriteTool;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SystemConfigReadTool::class)]
#[CoversClass(SystemConfigWriteTool::class)]
#[CoversClass(ConsoleCommandTool::class)]
class ConfigTroubleshootingScenarioTest extends McpScenarioTestCase
{
    public function testUS7ReadListingConfig(): void
    {
        $output = ($this->systemConfigReadTool)('core.listing');
        $data = $this->decodeToolOutput($output);

        static::assertSame('core.listing', $data['data']['domain']);
        static::assertIsArray($data['data']['values']);
    }

    public function testUS7WriteConfigDryRun(): void
    {
        $output = ($this->systemConfigWriteTool)(
            key: 'core.listing.defaultSorting',
            value: '"name-asc"',
            dryRun: true,
        );

        $data = $this->decodeToolOutput($output);

        static::assertTrue($data['_meta']['dryRun']);
        static::assertArrayHasKey('oldValue', $data['data']);
        static::assertSame('name-asc', $data['data']['newValue']);

        $verifyOutput = ($this->systemConfigReadTool)('core.listing.defaultSorting');
        $verifyData = $this->decodeToolOutput($verifyOutput);
        static::assertNotSame('name-asc', $verifyData['data']['value'], 'Dry run should not persist changes');
    }

    public function testUS8PluginList(): void
    {
        $output = ($this->consoleCommandTool)('plugin:list');
        $data = $this->decodeToolOutput($output);

        static::assertSame(0, $data['data']['exitCode']);
    }

    public function testUS9ConsoleCommandExecution(): void
    {
        $output = ($this->consoleCommandTool)('scheduled-task:list');
        $data = $this->decodeToolOutput($output);

        static::assertSame(0, $data['data']['exitCode']);
    }
}
