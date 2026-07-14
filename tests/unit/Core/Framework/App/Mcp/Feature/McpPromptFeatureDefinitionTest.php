<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Mcp\Feature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Feature\TranslatedString;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Mcp\Feature\McpPromptConfig;
use Shopware\Core\Framework\App\Mcp\Feature\McpPromptFeatureDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[CoversClass(McpPromptFeatureDefinition::class)]
#[Package('framework')]
class McpPromptFeatureDefinitionTest extends TestCase
{
    private McpPromptFeatureDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new McpPromptFeatureDefinition();
    }

    public function testType(): void
    {
        static::assertSame('mcp_prompt', $this->definition->getType());
        static::assertSame(McpPromptConfig::class, $this->definition->getConfigClass());
    }

    public function testExtractReturnsEmptyWhenNoMcpFile(): void
    {
        static::assertSame([], $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__),
            'en-GB',
        ));
    }

    public function testExtractReadsPromptsFromMcpXml(): void
    {
        $configs = $this->definition->fromApp(
            static::createStub(Manifest::class),
            new Filesystem(__DIR__ . '/../../_fixtures'),
            'en-GB',
        );

        static::assertCount(1, $configs);
        $config = $configs[0];
        static::assertSame('order-context', $config->name);
        static::assertSame('https://app.example.com/mcp/prompt/order-context', $config->url);
        static::assertSame('Order Context', $config->label->forLocale('en-GB'));
        static::assertSame('Context for orders', $config->description->forLocale('en-GB'));
    }

    public function testPayloadRoundTrip(): void
    {
        $declared = new McpPromptConfig(
            'order-context',
            'https://app.example.com/mcp/prompt/order-context',
            new TranslatedString(['en-GB' => 'Order Context']),
            new TranslatedString(['en-GB' => 'Context for orders']),
        );

        $hydrated = $this->definition->fromPayload($this->definition->toPayload($declared, null));

        static::assertEquals($declared, $hydrated);
    }
}
