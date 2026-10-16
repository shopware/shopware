<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Result;

use Mcp\Capability\Registry\ElementReference;
use Mcp\Capability\Registry\ReferenceHandlerInterface;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Server\RequestContext;
use Mcp\Server\Session\SessionInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Wraps the SDK's reference handler so every tool result, from core, plugins, bundles and apps, goes
 * through {@see McpToolResultRenderer}. A tool that returns a {@see McpToolResult} is rendered
 * directly; one that returns the legacy `{"success": …}` string is parsed first. Anything else, and
 * every prompt and resource, is passed through unchanged. Wired onto both MCP servers by
 * McpToolResultRendererCompilerPass.
 */
#[Package('framework')]
class McpToolResultReferenceHandler implements ReferenceHandlerInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ReferenceHandlerInterface $inner,
        private readonly McpToolResultRenderer $renderer,
        private readonly McpToolResultParser $parser,
    ) {
    }

    public function handle(ElementReference $reference, array $arguments): mixed
    {
        $result = $this->inner->handle($reference, $arguments);

        if (!$reference instanceof ToolReference) {
            return $result;
        }

        if ($result instanceof McpToolResult) {
            return $this->renderer->render($result, $this->protocolVersion($arguments));
        }

        if (\is_string($result) && ($parsed = $this->parser->parse($result)) !== null) {
            return $this->renderer->render($parsed, $this->protocolVersion($arguments), $result);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function protocolVersion(array $arguments): ProtocolVersion
    {
        $session = $arguments['_session'] ?? null;
        $request = $arguments['_request'] ?? null;

        if (!$session instanceof SessionInterface || !$request instanceof Request) {
            return ProtocolVersion::latestHandshake();
        }

        return (new RequestContext($session, $request))->getProtocolVersion();
    }
}
