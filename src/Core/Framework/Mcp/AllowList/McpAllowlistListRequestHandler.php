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
use Mcp\Schema\Resource;
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
     * @return Response<ListToolsResult>
     */
    private function handleListTools(ListToolsRequest $request): Response
    {
        $allowlist = $this->allowlistProvider->forCurrentRequest();

        if ($allowlist->tools === null) {
            $page = $this->registry->getTools($this->pageSize, $request->cursor);

            return new Response($request->getId(), new ListToolsResult($page->references, $page->nextCursor));
        }

        $tools = array_values(array_filter(
            $this->registry->getTools()->references,
            static fn (Tool $tool): bool => \in_array($tool->name, $allowlist->tools, true),
        ));

        [$page, $nextCursor] = $this->paginate($tools, $request->cursor);

        return new Response($request->getId(), new ListToolsResult($page, $nextCursor));
    }

    /**
     * @return Response<ListResourcesResult>
     */
    private function handleListResources(ListResourcesRequest $request): Response
    {
        $allowlist = $this->allowlistProvider->forCurrentRequest();

        if ($allowlist->resources === null) {
            $page = $this->registry->getResources($this->pageSize, $request->cursor);

            return new Response($request->getId(), new ListResourcesResult($page->references, $page->nextCursor));
        }

        $resources = array_values(array_filter(
            $this->registry->getResources()->references,
            static fn (Resource $resource): bool => \in_array($resource->uri, $allowlist->resources, true),
        ));

        [$page, $nextCursor] = $this->paginate($resources, $request->cursor);

        return new Response($request->getId(), new ListResourcesResult($page, $nextCursor));
    }

    /**
     * @return Response<ListPromptsResult>
     */
    private function handleListPrompts(ListPromptsRequest $request): Response
    {
        $allowlist = $this->allowlistProvider->forCurrentRequest();

        if ($allowlist->prompts === null) {
            $page = $this->registry->getPrompts($this->pageSize, $request->cursor);

            return new Response($request->getId(), new ListPromptsResult($page->references, $page->nextCursor));
        }

        $prompts = array_values(array_filter(
            $this->registry->getPrompts()->references,
            static fn (Prompt $prompt): bool => \in_array($prompt->name, $allowlist->prompts, true),
        ));

        [$page, $nextCursor] = $this->paginate($prompts, $request->cursor);

        return new Response($request->getId(), new ListPromptsResult($page, $nextCursor));
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

    private function decodeCursor(?string $cursor, int $totalItems): int
    {
        if ($cursor === null) {
            return 0;
        }

        $decodedCursor = base64_decode($cursor, true);

        if ($decodedCursor === false || !is_numeric($decodedCursor)) {
            throw new InvalidCursorException($cursor);
        }

        $offset = (int) $decodedCursor;

        if ($offset < 0 || $offset > $totalItems) {
            throw new InvalidCursorException($cursor);
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
