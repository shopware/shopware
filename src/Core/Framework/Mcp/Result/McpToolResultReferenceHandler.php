<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Result;

use Mcp\Capability\Registry\ElementReference;
use Mcp\Capability\Registry\ReferenceHandlerInterface;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Server\RequestContext;
use Mcp\Server\Session\SessionInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Wraps the SDK's reference handler so every tool result, from core, plugins, bundles and apps, goes
 * through {@see McpToolResultRenderer}. A tool that returns a {@see McpToolResult} is rendered
 * directly; one that returns the legacy `{"success": …}` string is parsed first. Anything else, and
 * every prompt and resource, is passed through unchanged.
 *
 * The legacy string is deprecated for PHP tools, whether built with McpToolResponse::success() and the
 * other string helpers or by hand, so this is where a deprecation is reported for it. App tools keep the
 * envelope as their response format and are not reported. Wired onto both MCP servers by
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

        // The call is echoed with a stored result, see McpToolResultRenderer.
        $request = $arguments['_request'] ?? null;
        $call = $request instanceof CallToolRequest ? $request : null;

        $hasOutputSchema = $reference->tool->outputSchema !== null;

        if ($result instanceof McpToolResult) {
            return $this->renderer->render($result, $this->protocolVersion($arguments), call: $call, hasOutputSchema: $hasOutputSchema);
        }

        if (\is_string($result) && ($parsed = $this->parser->parse($result)) !== null) {
            if ($this->isClassTool($reference)) {
                Feature::triggerDeprecationOrThrow(
                    'v6.9.0.0',
                    \sprintf(
                        'MCP tool "%s" returned the legacy {"success": …} JSON string, which is deprecated and will be removed in v6.9.0.0. Return a "%s" instead.',
                        $reference->tool->name,
                        McpToolResult::class,
                    ),
                    silentUntil: 'v6.8.0.0',
                );
            }

            return $this->renderer->render($parsed, $this->protocolVersion($arguments), $result, $call, $hasOutputSchema);
        }

        return $result;
    }

    /**
     * PHP tools, from core, plugins and bundles, are registered with their class and method. App tools are
     * registered as a closure by AppMcpToolLoader, which forwards the call to the app's endpoint.
     */
    private function isClassTool(ToolReference $reference): bool
    {
        return !$reference->handler instanceof \Closure;
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
