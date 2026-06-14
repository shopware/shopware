<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp\Scenario;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
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

    public function testUS28MaintenanceMode(): void
    {
        $output = ($this->entityUpsertTool)(
            entity: 'sales_channel',
            payload: json_encode([
                'id' => TestDefaults::SALES_CHANNEL,
                'maintenance' => true,
            ], \JSON_THROW_ON_ERROR),
            dryRun: false,
        );

        $data = $this->decodeToolOutput($output);
        static::assertFalse($data['_meta']['dryRun']);

        $readOutput = ($this->entityReadTool)(
            entity: 'sales_channel',
            id: TestDefaults::SALES_CHANNEL,
        );

        $readData = $this->decodeToolOutput($readOutput);
        static::assertTrue($readData['data']['maintenance'], 'Sales channel should be in maintenance mode');

        ($this->entityUpsertTool)(
            entity: 'sales_channel',
            payload: json_encode([
                'id' => TestDefaults::SALES_CHANNEL,
                'maintenance' => false,
            ], \JSON_THROW_ON_ERROR),
            dryRun: false,
        );
    }
}
