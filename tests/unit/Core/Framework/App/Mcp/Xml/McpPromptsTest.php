<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Mcp\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\App\Mcp\Xml\McpPrompt;
use Shopware\Core\Framework\App\Mcp\Xml\McpPrompts;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(McpPrompts::class)]
#[Package('framework')]
class McpPromptsTest extends TestCase
{
    public function testFromXmlParsesMultiplePrompts(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/../_fixtures/mcp.xml');

        $prompts = $mcp->getPrompts();
        static::assertNotNull($prompts);
        static::assertCount(2, $prompts->getPrompts());
        static::assertContainsOnlyInstancesOf(McpPrompt::class, $prompts->getPrompts());
    }

    public function testGetPromptsReturnsPromptsInOrder(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/../_fixtures/mcp.xml');

        $prompts = $mcp->getPrompts();
        static::assertNotNull($prompts);

        $names = array_map(
            static fn (McpPrompt $p) => $p->getName(),
            $prompts->getPrompts(),
        );

        static::assertSame(['order-context', 'product-context'], $names);
    }

    public function testFromArrayCreatesPrompts(): void
    {
        $prompt = McpPrompt::fromArray([
            'name' => 'test-prompt',
            'url' => 'https://example.com/prompt',
            'label' => ['en-GB' => 'Test'],
            'description' => [],
        ]);

        $prompts = McpPrompts::fromArray(['prompts' => [$prompt]]);

        static::assertCount(1, $prompts->getPrompts());
        static::assertSame('test-prompt', $prompts->getPrompts()[0]->getName());
    }
}
