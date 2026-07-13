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

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * @internal
 *
 * @implements RequestHandlerInterface<ListToolsResult|ListResourcesResult|ListPromptsResult>
 */
#[Package('framework')]
class McpAllowlistListRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly RegistryInterface $registry,
        private readonly McpAllowlistProvider $allowlistProvider,
        private readonly int $pageSize,
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
        $allowlist = $this->allowlistProvider->forCurrentRequest();

        if ($allowlist->tools === null) {
            $page = $this->registry->getTools($this->pageSize, $request->cursor);

            return $this->createResponse($request->getId(), new ListToolsResult($this->collectTools($page->references), $page->nextCursor));
        }

        $tools = $this->collectTools(
            $this->registry->getTools()->references,
            $allowlist->tools,
        );

        [$page, $nextCursor] = $this->paginate($tools, $request->cursor);

        return $this->createResponse($request->getId(), new ListToolsResult($page, $nextCursor));
    }

    /**
     * @return Response<ListToolsResult|ListResourcesResult|ListPromptsResult>
     */
    private function handleListResources(ListResourcesRequest $request): Response
    {
        $allowlist = $this->allowlistProvider->forCurrentRequest();

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
        $allowlist = $this->allowlistProvider->forCurrentRequest();

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
