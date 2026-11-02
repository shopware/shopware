<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Result;

use Mcp\Capability\Registry\ElementReference;
use Mcp\Capability\Registry\PromptReference;
use Mcp\Capability\Registry\ReferenceHandlerInterface;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Prompt;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Tool;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\Session;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Result\McpToolResult;
use Shopware\Core\Framework\Mcp\Result\McpToolResultParser;
use Shopware\Core\Framework\Mcp\Result\McpToolResultReferenceHandler;
use Shopware\Core\Framework\Mcp\Result\McpToolResultRenderer;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolResultReferenceHandler::class)]
class McpToolResultReferenceHandlerTest extends TestCase
{
    public function testRendersAResultObject(): void
    {
        $result = $this->handler(McpToolResult::success(['a' => 1]))->handle($this->toolReference(), []);

        static::assertInstanceOf(CallToolResult::class, $result);
        static::assertSame(['a' => 1], $result->structuredContent);
    }

    public function testRendersTheLegacyEnvelopeAndKeepsItsText(): void
    {
        $result = $this->handler('{"success":false,"error":"Nope","code":"not_found"}')->handle($this->toolReference(), []);

        static::assertInstanceOf(CallToolResult::class, $result);
        static::assertTrue($result->isError);
    }

    public function testPassesOtherToolResultsThrough(): void
    {
        static::assertSame('plain text', $this->handler('plain text')->handle($this->toolReference(), []));
    }

    public function testLeavesPromptsAlone(): void
    {
        $prompt = new PromptReference(new Prompt('p'), static fn (): string => '');

        static::assertSame('{"success":true}', $this->handler('{"success":true}')->handle($prompt, []));
    }

    public function testUsesTheNegotiatedProtocolVersion(): void
    {
        $session = new Session(new InMemorySessionStore());
        $session->set('protocol_version', ProtocolVersion::V2026_07_28->value);

        $result = $this->handler(McpToolResult::success([1, 2]))->handle($this->toolReference(), [
            '_session' => $session,
            '_request' => CallToolRequest::fromArray(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 't', 'arguments' => []]]),
        ]);

        static::assertInstanceOf(CallToolResult::class, $result);
        static::assertSame([1, 2], $result->structuredContent, 'from 2026-07-28 on a list is sent unwrapped');
    }

    private function handler(mixed $innerResult): McpToolResultReferenceHandler
    {
        $inner = static::createStub(ReferenceHandlerInterface::class);
        $inner->method('handle')->willReturn($innerResult);

        return new McpToolResultReferenceHandler($inner, new McpToolResultRenderer(new MockClock()), new McpToolResultParser());
    }

    private function toolReference(): ElementReference
    {
        return new ToolReference(new Tool('t', null, ['type' => 'object', 'properties' => [], 'required' => null], null, null), static fn (): string => '');
    }
}
