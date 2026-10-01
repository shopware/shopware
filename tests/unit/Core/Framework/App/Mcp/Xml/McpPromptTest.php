<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Mcp\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\App\Mcp\Xml\McpPrompt;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpPrompt::class)]
class McpPromptTest extends TestCase
{
    public function testFromXmlParsesAllFields(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/../_fixtures/mcp.xml');

        $prompts = $mcp->getPrompts();
        static::assertNotNull($prompts);

        $prompt = $prompts->getPrompts()[0];
        static::assertSame('order-context', $prompt->getName());
        static::assertSame('https://app.example.com/mcp/prompt/order-context', $prompt->getUrl());
        static::assertSame([
            'en-GB' => 'Order Context',
            'de-DE' => 'Bestellungskontext',
        ], $prompt->getLabel());
        static::assertSame([
            'en-GB' => 'Context for order management',
            'de-DE' => 'Kontext für die Bestellungsverwaltung',
        ], $prompt->getDescription());
    }

    public function testPromptWithoutDescriptionReturnsEmptyArray(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/../_fixtures/mcp.xml');

        $prompts = $mcp->getPrompts();
        static::assertNotNull($prompts);

        $prompt = $prompts->getPrompts()[1];
        static::assertSame('product-context', $prompt->getName());
        static::assertSame([], $prompt->getDescription());
    }

    public function testFromArraySetsProperties(): void
    {
        $prompt = McpPrompt::fromArray([
            'name' => 'test',
            'url' => 'https://test.example.com/prompt',
            'label' => ['en-GB' => 'Test'],
            'description' => [],
        ]);

        static::assertSame('test', $prompt->getName());
        static::assertSame('https://test.example.com/prompt', $prompt->getUrl());
        static::assertSame(['en-GB' => 'Test'], $prompt->getLabel());
        static::assertSame([], $prompt->getDescription());
    }
}
