<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Mcp\Feature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Feature\TranslatedString;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Mcp\Feature\McpToolConfig;
use Shopware\Core\Framework\App\Mcp\Feature\McpToolFeatureDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[CoversClass(McpToolFeatureDefinition::class)]
#[Package('framework')]
class McpToolFeatureDefinitionTest extends TestCase
{
    private McpToolFeatureDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new McpToolFeatureDefinition();
    }

    public function testType(): void
    {
        static::assertSame('mcp_tool', $this->definition->getType());
        static::assertSame(McpToolConfig::class, $this->definition->getConfigClass());
    }

    public function testExtractReturnsEmptyWhenNoMcpFile(): void
    {
        static::assertSame([], $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__),
            'en-GB',
        ));
    }

    public function testExtractReadsToolsFromMcpXml(): void
    {
        $configs = $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'en-GB',
        );

        static::assertCount(1, $configs);
        $config = $configs[0];
        static::assertSame('sync-orders', $config->name);
        static::assertSame('https://app.example.com/mcp/sync-orders', $config->url);
        static::assertSame(['order:read', 'order:update'], $config->requiredPrivileges);
        static::assertSame(
            ['since' => ['type' => 'string', 'description' => 'ISO date', 'required' => true]],
            $config->inputSchema,
        );
        static::assertSame('Sync Orders', $config->label->forLocale('en-GB'));
        static::assertSame('Bestellungen synchronisieren', $config->label->forLocale('de-DE'));
        static::assertSame('Syncs orders', $config->description->forLocale('en-GB'));
    }

    public function testPayloadRoundTripIgnoresStored(): void
    {
        $declared = new McpToolConfig(
            'sync-orders',
            'https://app.example.com/mcp/sync-orders',
            ['order:read'],
            ['since' => ['type' => 'string', 'required' => true]],
            new TranslatedString(['en-GB' => 'Sync Orders']),
            new TranslatedString(['en-GB' => 'Syncs orders']),
        );

        $stored = new McpToolConfig('sync-orders', 'https://stale.example.com', [], null, new TranslatedString(['en-GB' => 'Old']), new TranslatedString([]));

        $payload = $this->definition->toPayload($declared, $stored);
        $hydrated = $this->definition->fromPayload($payload);

        static::assertEquals($declared, $hydrated);
    }
}
