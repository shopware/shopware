<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Controller\McpServerController;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Provides a unified response envelope for MCP tools.
 *
 * All tools must extend this class and use success() and error() to build
 * return values so AI clients receive a predictable JSON structure.
 *
 * Error handling for extension developers:
 * - Unhandled exceptions propagate to the MCP SDK's generic handler and produce
 *   a generic "Error while executing tool" message to the AI client.
 * - To expose error details, either throw a ToolCallException or return $this->error().
 * - Use $this->error() for expected/business errors (missing privilege, not found, etc.).
 * - Let unexpected exceptions propagate so they appear in logs without leaking internals.
 */
#[Package('framework')]
abstract class McpToolResponse
{
    private const MAX_RESPONSE_SIZE = 100_000;

    /**
     * Threshold below MAX_RESPONSE_SIZE at which _meta.responseSize is included so the LLM
     * can see the cost of the current query and learn to use tighter includes/limit next time.
     */
    private const RESPONSE_SIZE_HINT_THRESHOLD = 20_000;

    private ?ToolResultCacheStorage $toolResultCache = null;

    private ?RequestStack $requestStack = null;

    private ?LoggerInterface $mcpLogger = null;

    /**
     * @internal called by the DI container via mcp.php instanceof configurator
     */
    public function setToolResultCache(ToolResultCacheStorage $cache, RequestStack $requestStack, LoggerInterface $logger): void
    {
        $this->toolResultCache = $cache;
        $this->requestStack = $requestStack;
        $this->mcpLogger = $logger;
    }

    /**
     * @param array<string, mixed>|list<mixed> $data
     * @param array<string, mixed> $meta
     */
    protected function success(array $data, array $meta = []): string
    {
        $response = ['success' => true, 'data' => $data];

        if ($meta !== []) {
            $response['_meta'] = $meta;
        }

        $json = json_encode($response, \JSON_THROW_ON_ERROR);
        $size = \strlen($json);

        if ($size <= self::MAX_RESPONSE_SIZE) {
            if ($size >= self::RESPONSE_SIZE_HINT_THRESHOLD) {
                $response['_meta'] = array_merge($meta, ['responseSize' => $size]);
                $json = json_encode($response, \JSON_THROW_ON_ERROR);
            }

            return $json;
        }

        if ($this->toolResultCache !== null && $this->requestStack !== null) {
            $request = $this->requestStack->getCurrentRequest();
            $sessionId = $request?->headers->get('Mcp-Session-Id') ?? '';

            if ($sessionId !== '' && $request !== null) {
                $uuid = $this->toolResultCache->store($sessionId, $json);
                $resourceUri = 'shopware://tool-result/' . $uuid;

                $this->mcpLogger?->debug('MCP tool response stored as resource (oversized)', [
                    'tool' => static::class,
                    'size' => $size,
                    'resourceUri' => $resourceUri,
                ]);

                $oversizedMeta = array_merge($meta, [
                    'resourceUri' => $resourceUri,
                    'responseSize' => $size,
                    'note' => 'Response too large for inline delivery. '
                        . 'Prefer re-running the tool with tighter "includes" or a lower "limit" to get a smaller inline result. '
                        . 'If you need the full data as-is, fetch it via resources/read using the URI in _meta.resourceUri.',
                ]);

                $query = $this->extractQuery($request);
                if ($query !== null) {
                    $oversizedMeta['query'] = $query;
                }

                return json_encode([
                    'success' => true,
                    'data' => null,
                    '_meta' => $oversizedMeta,
                ], \JSON_THROW_ON_ERROR);
            }
        }

        // Fallback when no active session (e.g. CLI or test context): return inline as-is.
        return $json;
    }

    protected function error(string $message): string
    {
        return json_encode(['success' => false, 'error' => $message], \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<mixed>|string Returns the decoded array on success, or an error JSON string on failure.
     */
    protected function decodeJsonOrError(string $json, string $fieldName = 'input'): array|string
    {
        try {
            $result = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->error(\sprintf('Invalid JSON for "%s": %s', $fieldName, $e->getMessage()));
        }

        if (!\is_array($result)) {
            return $this->error(\sprintf('"%s" must be a JSON object or array', $fieldName));
        }

        return $result;
    }

    /**
     * @return string|null Error JSON string if a privilege is missing, null if all granted
     */
    protected function requirePrivilege(Context $context, string ...$privileges): ?string
    {
        foreach ($privileges as $privilege) {
            if (!$context->isAllowed($privilege)) {
                return $this->error(\sprintf('Missing privilege: %s', $privilege));
            }
        }

        return null;
    }

    /**
     * Executes an operation within a transaction that is always rolled back (dry-run preview).
     *
     * The context receives SKIP_TRIGGER_FLOW to prevent flows from firing during the
     * dry-run. Note that with Redis-based delayed cache invalidation, DAL writes may
     * still enqueue invalidations that are not reverted by the DB rollback.
     *
     * @param callable(): string $operation Must return the JSON result string
     */
    protected function executeWithDryRun(Connection $connection, Context $context, callable $operation): string
    {
        $context->addState(Context::SKIP_TRIGGER_FLOW);

        $connection->beginTransaction();

        try {
            $result = $operation();
        } catch (\Throwable $e) {
            $result = $this->error($e->getMessage());
        }

        $context->removeState(Context::SKIP_TRIGGER_FLOW);

        try {
            $connection->rollBack();
        } catch (\Throwable $rollbackException) {
            return $this->error(\sprintf('Dry-run rollback failed: data may have been persisted. %s', $rollbackException->getMessage()));
        }

        return $result;
    }

    /**
     * @return list<array{entity: string, ids: list<string>, operation: string}>
     */
    protected function formatWriteEvents(EntityWrittenContainerEvent $events, string $operation): array
    {
        $result = [];
        foreach ($events->getEvents()?->getElements() ?? [] as $event) {
            $result[] = [
                'entity' => $event->getEntityName(),
                'ids' => $event->getIds(),
                'operation' => $operation,
            ];
        }

        return $result;
    }

    /**
     * @return array{tool: string, arguments: array<string, mixed>}|null
     */
    private function extractQuery(Request $request): ?array
    {
        $body = $request->attributes->get(McpServerController::ATTRIBUTE_JSONRPC_BODY);
        if (!\is_array($body) || ($body['method'] ?? null) !== 'tools/call') {
            return null;
        }

        $params = \is_array($body['params'] ?? null) ? $body['params'] : [];
        $tool = \is_string($params['name'] ?? null) ? $params['name'] : '';
        $arguments = \is_array($params['arguments'] ?? null) ? $params['arguments'] : [];

        if ($tool === '') {
            return null;
        }

        return ['tool' => $tool, 'arguments' => $arguments];
    }
}
