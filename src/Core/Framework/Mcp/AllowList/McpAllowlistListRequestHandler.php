<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\AllowList;

use Mcp\Capability\RegistryInterface;
use Mcp\Exception\InvalidCursorException;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Prompt;
use Mcp\Schema\Request\ListPromptsRequest;
use Mcp\Schema\Request\ListResourcesRequest;
use Mcp\Schema\Request\ListToolsRequest;
use Mcp\Schema\ResourceDefinition as McpResource;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Result\ListPromptsResult;
use Mcp\Schema\Result\ListResourcesResult;
use Mcp\Schema\Result\ListToolsResult;
use Mcp\Schema\Tool;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * @implements RequestHandlerInterface<ListToolsResult|ListResourcesResult|ListPromptsResult>
 */
#[Package('framework')]
class McpAllowlistListRequestHandler implements RequestHandlerInterface
{
    private const TOOL_SEARCH = 'shopware-tool-search';

    /**
     * The server-owned discovery meta-tools. They are always advertised and always callable,
     * independent of the per-integration allowlist, so the guaranteed discovery path stays usable
     * even for integrations whose allowlist omits them.
     */
    private const DISCOVERY_META_TOOLS = [
        self::TOOL_SEARCH,
        McpToolsetRegistry::LIST_TOOLSETS_TOOL,
        McpToolsetRegistry::ENABLE_TOOLSET_TOOL,
    ];

    /**
     * @param list<string> $advertisedTools
     */
    public function __construct(
        private readonly RegistryInterface $registry,
        private readonly ?McpAllowlistProvider $allowlistProvider,
        private readonly int $pageSize,
        private readonly array $advertisedTools = [self::TOOL_SEARCH],
        private readonly ?McpToolsetRegistry $toolsetRegistry = null,
        private readonly ?McpToolsetSessionStorage $toolsetSessionStorage = null,
        private readonly ?RequestStack $requestStack = null,
    ) {
    }

    #[\Override]
    public function supports(Request $request): bool
    {
        return $request instanceof ListToolsRequest
            || $request instanceof ListResourcesRequest
            || $request instanceof ListPromptsRequest;
    }

    /**
     * @return Response<ListToolsResult|ListResourcesResult|ListPromptsResult>
     */
    #[\Override]
    public function handle(Request $request, SessionInterface $session): Response
    {
        if ($request instanceof ListToolsRequest) {
            return $this->handleListTools($request);
        }

        if ($request instanceof ListResourcesRequest) {
            return $this->handleListResources($request);
        }

        \assert($request instanceof ListPromptsRequest);

        return $this->handleListPrompts($request);
    }

    /**
     * @return Response<ListToolsResult|ListResourcesResult|ListPromptsResult>
     */
    private function handleListTools(ListToolsRequest $request): Response
    {
        $allowlist = $this->allowlistProvider?->forCurrentRequest() ?? McpAllowlist::unrestricted();

        $tools = $this->collectTools(
            $this->registry->getTools()->references,
            $this->visibleToolNames($allowlist),
        );

        [$page, $nextCursor] = $this->paginate($tools, $request->cursor);

        return $this->createResponse($request->getId(), new ListToolsResult($page, $nextCursor));
    }

    /**
     * @return list<string>
     */
    private function visibleToolNames(McpAllowlist $allowlist): array
    {
        $advertisedTools = array_merge($this->advertisedTools, $this->toolsetToolsForSession());

        if (!\in_array(self::TOOL_SEARCH, $advertisedTools, true)) {
            array_unshift($advertisedTools, self::TOOL_SEARCH);
        }

        $advertisedTools = array_values(array_unique($advertisedTools));

        if ($allowlist->tools === null) {
            return $advertisedTools;
        }

        // The server-owned discovery meta-tools stay advertised even when the integration's
        // allowlist omits them, so the guaranteed discovery path (toolsets-list -> toolset-enable
        // -> listChanged) keeps working. Every other tool remains bounded by the allowlist.
        return array_values(array_unique(array_merge(
            array_values(array_intersect($advertisedTools, self::DISCOVERY_META_TOOLS)),
            array_values(array_intersect($advertisedTools, $allowlist->tools)),
        )));
    }

    /**
     * @return list<string>
     */
    private function toolsetToolsForSession(): array
    {
        if ($this->toolsetRegistry === null) {
            return [];
        }

        $sessionId = $this->requestStack?->getCurrentRequest()?->headers->get('Mcp-Session-Id') ?? '';
        if ($sessionId === '' || $this->toolsetSessionStorage === null) {
            return $this->toolsetRegistry->advertisedTools([]);
        }

        return $this->toolsetRegistry->advertisedTools($this->toolsetSessionStorage->enabledToolsets($sessionId));
    }

    /**
     * @return Response<ListToolsResult|ListResourcesResult|ListPromptsResult>
     */
    private function handleListResources(ListResourcesRequest $request): Response
    {
        $allowlist = $this->allowlistProvider?->forCurrentRequest() ?? McpAllowlist::unrestricted();

        if ($allowlist->resources === null) {
            $page = $this->registry->getResources($this->pageSize, $request->cursor);

            return $this->createResponse($request->getId(), new ListResourcesResult($this->collectResources($page->references), $page->nextCursor));
        }

        $resources = $this->collectResources(
            $this->registry->getResources()->references,
            $allowlist->resources,
        );

        [$page, $nextCursor] = $this->paginate($resources, $request->cursor);

        return $this->createResponse($request->getId(), new ListResourcesResult($page, $nextCursor));
    }

    /**
     * @return Response<ListToolsResult|ListResourcesResult|ListPromptsResult>
     */
    private function handleListPrompts(ListPromptsRequest $request): Response
    {
        $allowlist = $this->allowlistProvider?->forCurrentRequest() ?? McpAllowlist::unrestricted();

        if ($allowlist->prompts === null) {
            $page = $this->registry->getPrompts($this->pageSize, $request->cursor);

            return $this->createResponse($request->getId(), new ListPromptsResult($this->collectPrompts($page->references), $page->nextCursor));
        }

        $prompts = $this->collectPrompts(
            $this->registry->getPrompts()->references,
            $allowlist->prompts,
        );

        [$page, $nextCursor] = $this->paginate($prompts, $request->cursor);

        return $this->createResponse($request->getId(), new ListPromptsResult($page, $nextCursor));
    }

    /**
     * @return Response<ListToolsResult|ListResourcesResult|ListPromptsResult>
     */
    private function createResponse(string|int $id, ListToolsResult|ListResourcesResult|ListPromptsResult $result): Response
    {
        return new Response($id, $result);
    }

    /**
     * @template T
     *
     * @param list<T> $items
     *
     * @return array{list<T>, ?string}
     */
    private function paginate(array $items, ?string $cursor): array
    {
        $offset = $this->decodeCursor($cursor, \count($items));

        return [
            array_values(\array_slice($items, $offset, $this->pageSize)),
            $this->calculateNextCursor(\count($items), $offset),
        ];
    }

    /**
     * @param array<int|string, McpResource|Prompt|ResourceTemplate|Tool> $references
     * @param list<string>|null $allowedNames
     *
     * @return list<Tool>
     */
    private function collectTools(array $references, ?array $allowedNames = null): array
    {
        $tools = [];

        foreach ($references as $reference) {
            if (!$reference instanceof Tool) {
                continue;
            }

            if ($allowedNames !== null && !\in_array($reference->name, $allowedNames, true)) {
                continue;
            }

            $tools[] = $reference;
        }

        return $tools;
    }

    /**
     * @param array<int|string, McpResource|Prompt|ResourceTemplate|Tool> $references
     * @param list<string>|null $allowedUris
     *
     * @return list<McpResource>
     */
    private function collectResources(array $references, ?array $allowedUris = null): array
    {
        $resources = [];

        foreach ($references as $reference) {
            if (!$reference instanceof McpResource) {
                continue;
            }

            if ($allowedUris !== null && !\in_array($reference->uri, $allowedUris, true)) {
                continue;
            }

            $resources[] = $reference;
        }

        return $resources;
    }

    /**
     * @param array<int|string, McpResource|Prompt|ResourceTemplate|Tool> $references
     * @param list<string>|null $allowedNames
     *
     * @return list<Prompt>
     */
    private function collectPrompts(array $references, ?array $allowedNames = null): array
    {
        $prompts = [];

        foreach ($references as $reference) {
            if (!$reference instanceof Prompt) {
                continue;
            }

            if ($allowedNames !== null && !\in_array($reference->name, $allowedNames, true)) {
                continue;
            }

            $prompts[] = $reference;
        }

        return $prompts;
    }

    private function decodeCursor(?string $cursor, int $totalItems): int
    {
        if ($cursor === null) {
            return 0;
        }

        $decodedCursor = base64_decode($cursor, true);

        if ($decodedCursor === false || !is_numeric($decodedCursor)) {
            throw new InvalidCursorException($cursor); // @phpstan-ignore shopware.domainException (MCP SDK request handlers use this SDK exception for invalid cursors.)
        }

        $offset = (int) $decodedCursor;

        if ($offset < 0 || $offset > $totalItems) {
            throw new InvalidCursorException($cursor); // @phpstan-ignore shopware.domainException (MCP SDK request handlers use this SDK exception for invalid cursors.)
        }

        return $offset;
    }

    private function calculateNextCursor(int $totalItems, int $offset): ?string
    {
        $nextOffset = $offset + $this->pageSize;

        if ($nextOffset < $totalItems) {
            return base64_encode((string) $nextOffset);
        }

        return null;
    }
}
