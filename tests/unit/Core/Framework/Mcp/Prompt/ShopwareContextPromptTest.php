<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Prompt;

use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server\Stateless\RequestMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Prompt\ShopwareContextPrompt;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShopwareContextPrompt::class)]
class ShopwareContextPromptTest extends TestCase
{
    public function testInvokeReturnsMessagesWithRoleAndContent(): void
    {
        $prompt = new ShopwareContextPrompt();
        $result = ($prompt)();

        static::assertIsArray($result);
        static::assertNotCount(0, $result);
        static::assertArrayHasKey('role', $result[0]);
        static::assertArrayHasKey('content', $result[0]);
        static::assertSame('user', $result[0]['role']);
    }

    public function testContentContainsKeyPhrases(): void
    {
        $prompt = new ShopwareContextPrompt();
        $result = ($prompt)();

        $content = $result[0]['content'];
        static::assertStringContainsString('Shopware', $content);
        static::assertStringContainsString('entity', $content);
        static::assertStringContainsString('shopware-tool-search', $content);
        static::assertStringContainsString('shopware-toolsets-list', $content);
        static::assertStringContainsString('shopware-toolset-enable', $content);
        static::assertStringContainsString('allowlist and ACL permissions remain the security boundary', $content);
    }

    public function testHandshakeRequestKeepsTheToolsetSessionGuidance(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/api/_mcp', 'POST', content: '{"jsonrpc":"2.0","id":1,"method":"prompts/get","params":{"name":"shopware-context"}}'));

        static::assertSame((new ShopwareContextPrompt())(), (new ShopwareContextPrompt($requestStack))());
    }

    public function testStatelessEraOnlyNamesToolsTheEraOffers(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/api/_mcp', 'POST', content: json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'prompts/get',
            'params' => [
                'name' => 'shopware-context',
                '_meta' => [RequestMeta::PROTOCOL_VERSION => ProtocolVersion::V2026_07_28->value],
            ],
        ], \JSON_THROW_ON_ERROR)));

        $content = (new ShopwareContextPrompt($requestStack))()[0]['content'];
        $handshake = (new ShopwareContextPrompt())()[0]['content'];

        static::assertStringNotContainsString('shopware-toolsets-list', $content);
        static::assertStringNotContainsString('shopware-toolset-enable', $content);
        static::assertStringNotContainsString('enable the matching toolset', $content);
        static::assertStringContainsString('shopware-tool-search', $content);
        static::assertStringContainsString('?toolsets=', $content);
        static::assertStringContainsString('allowlist and ACL permissions remain the security boundary', $content);
        // only the discovery parts differ
        static::assertSame(strstr($handshake, '## Key concepts'), strstr($content, '## Key concepts'));
    }
}
