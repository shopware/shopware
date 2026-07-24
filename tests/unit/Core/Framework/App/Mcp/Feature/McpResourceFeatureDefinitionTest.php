<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Mcp\Feature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Feature\TranslatedString;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Mcp\Feature\McpResourceConfig;
use Shopware\Core\Framework\App\Mcp\Feature\McpResourceFeatureDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[CoversClass(McpResourceFeatureDefinition::class)]
#[Package('framework')]
class McpResourceFeatureDefinitionTest extends TestCase
{
    private McpResourceFeatureDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new McpResourceFeatureDefinition();
    }

    public function testType(): void
    {
        static::assertSame('mcp_resource', $this->definition->getType());
        static::assertSame(McpResourceConfig::class, $this->definition->getConfigClass());
    }

    public function testExtractReturnsEmptyWhenNoMcpFile(): void
    {
        static::assertSame([], $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__),
            'en-GB',
        ));
    }

    public function testExtractReadsResourcesFromMcpXml(): void
    {
        $configs = $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'en-GB',
        );

        static::assertCount(1, $configs);
        $config = $configs[0];
        static::assertSame('order-stats', $config->name);
        static::assertSame('app-example://order-stats', $config->uri);
        static::assertSame('https://app.example.com/mcp/resource/order-stats', $config->url);
        static::assertSame('application/json', $config->mimeType);
        static::assertSame('Order Stats', $config->label->forLocale('en-GB'));
        static::assertSame('Live order statistics', $config->description->forLocale('en-GB'));
    }

    public function testPayloadRoundTrip(): void
    {
        $declared = new McpResourceConfig(
            'order-stats',
            'app-example://order-stats',
            'https://app.example.com/mcp/resource/order-stats',
            'application/json',
            new TranslatedString(['en-GB' => 'Order Stats']),
            new TranslatedString(['en-GB' => 'Live order statistics']),
        );

        $hydrated = $this->definition->fromPayload($this->definition->toPayload($declared, null));

        static::assertEquals($declared, $hydrated);
    }
}
