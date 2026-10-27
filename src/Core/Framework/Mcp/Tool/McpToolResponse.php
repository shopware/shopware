<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Result\McpToolError;
use Shopware\Core\Framework\Mcp\Result\McpToolResult;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Util\Json;

/**
 * @experimental stableVersion:v6.8.0
 *
 * The base class of every MCP tool, with helpers that build its result.
 *
 * A tool returns a {@see McpToolResult}, built with successResult() and errorResult(), and declares
 * `__invoke(): McpToolResult`. McpToolResultRenderer turns it into the format of the negotiated protocol
 * revision: the MCP format (`structuredContent`, `isError`, the plain data as text) since 2025-06-18,
 * the legacy `{"success": …}` envelope before.
 *
 * Deprecated: returning the legacy `{"success": …}` string, as success(), error() and the other string
 * helpers build it, or as a tool builds it by hand. Such strings still work and are rendered like a
 * {@see McpToolResult}, but they can't carry a summary or error details. Use the helpers ending in
 * `Result` instead: successResult(), errorResult(), requirePrivilegeResult(), missingPrivilegesResult(),
 * invalidCriteriaResult(), decodeJsonOrErrorResult() and executeWithDryRunResult().
 *
 * Error handling for extension developers:
 * - Unhandled exceptions propagate to the MCP SDK's generic handler and produce
 *   a generic "Error while executing tool" message to the AI client.
 * - To expose error details, either throw a ToolCallException or return $this->errorResult().
 * - Use $this->errorResult() for expected/business errors (missing privilege, not found, etc.).
 * - Let unexpected exceptions propagate so they appear in logs without leaking internals.
 */
#[Package('framework')]
abstract class McpToolResponse
{
    /**
     * Size of the JSON at which _meta.responseSize is included so the LLM can see the cost of the
     * current query and learn to use tighter includes/limit next time. Whether a result is too large to
     * send inline is decided by the renderer, see McpToolResultRenderer.
     */
    private const RESPONSE_SIZE_HINT_THRESHOLD = 20_000;

    /**
     * A successful result. From 20 KB on, measured as the legacy envelope, the metadata gets `responseSize`.
     *
     * @param array<string, mixed> $meta
     * @param string|null $summary a short text for the model next to the data, for example "3 products updated"
     */
    protected function successResult(mixed $data, array $meta = [], ?string $summary = null): McpToolResult
    {
        $response = ['success' => true, 'data' => $data];

        if ($meta !== []) {
            $response['_meta'] = $meta;
        }

        $size = \strlen(Json::encode($response));

        if ($size >= self::RESPONSE_SIZE_HINT_THRESHOLD) {
            $meta = array_merge($meta, ['responseSize' => $size]);
        }

        return McpToolResult::success($data, $meta, $summary);
    }

    /**
     * @param string $code a stable code clients can match on, see {@see McpToolError}; it ends up in
     *                     `structuredContent.error.code` next to `isError: true`
     * @param array<string, mixed> $details what the model needs to fix the call, for example the rejected fields
     */
    protected function errorResult(string $message, string $code = McpToolError::TOOL_ERROR, array $details = []): McpToolResult
    {
        return McpToolResult::failure($message, $code, $details);
    }

    /**
     * Renders a `RequestCriteriaBuilder::fromArray()` failure as an error the
     * caller can act on, instead of letting it escape to the SDK's generic
     * "Error while executing tool".
     *
     * `SearchRequestException` carries one entry per rejected pointer, so each
     * detail is prefixed with it: "/aggregations/0/avg/field" names the element
     * that is wrong. Every other `ShopwareHttpException` is rendered by its own
     * message, because the builder reports bad input through several unrelated
     * classes: `DataAbstractionLayerException` directly for e.g.
     * `expectedArrayWithType()` on `{"includes":"id"}`,
     * `FrameworkException::associationNotFound()` for an unknown association,
     * and `ApiProtectionException` / `RuntimeFieldInCriteriaException` from
     * `ApiCriteriaValidator` for a field the caller may not query. All of them
     * name the offending part of the payload, so the message is what the caller
     * needs; only the exception class differs.
     */
    protected function invalidCriteriaResult(ShopwareHttpException $e): McpToolResult
    {
        if (!$e instanceof SearchRequestException) {
            return $this->errorResult($e->getMessage(), McpToolError::INVALID_ARGUMENTS);
        }

        $details = [];
        foreach ($e->getErrors() as $error) {
            $pointer = $error['source']['pointer'];
            $details[] = $pointer === '' ? $error['detail'] : \sprintf('%s: %s', $pointer, $error['detail']);
        }

        // An empty exception is not thrown by tryToThrow(), but getErrors() is a
        // generator over caller-supplied state and a message with nothing after
        // the colon would be worse than the generic one it replaces.
        if ($details === []) {
            return $this->errorResult($e->getMessage(), McpToolError::INVALID_ARGUMENTS);
        }

        return $this->errorResult(\sprintf('Invalid criteria: %s', \implode('; ', $details)), McpToolError::INVALID_ARGUMENTS);
    }

    /**
     * @return array<mixed>|McpToolResult the decoded array, or a failure if the JSON is malformed or not an array
     */
    protected function decodeJsonOrErrorResult(string $json, string $fieldName = 'input'): array|McpToolResult
    {
        try {
            $result = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->errorResult(\sprintf('Invalid JSON for "%s": %s', $fieldName, $e->getMessage()), McpToolError::INVALID_ARGUMENTS);
        }

        if (!\is_array($result)) {
            return $this->errorResult(\sprintf('"%s" must be a JSON object or array', $fieldName), McpToolError::INVALID_ARGUMENTS);
        }

        return $result;
    }

    /**
     * @return McpToolResult|null a failure if a privilege is missing, null if all are granted
     */
    protected function requirePrivilegeResult(Context $context, string ...$privileges): ?McpToolResult
    {
        foreach ($privileges as $privilege) {
            if (!$context->isAllowed($privilege)) {
                return $this->missingPrivilegesResult([$privilege]);
            }
        }

        return null;
    }

    /**
     * Canonical failure for privilege denials — all tools must use this
     * so clients can match on a single "Missing privilege:" prefix.
     *
     * @param non-empty-list<string> $privileges
     */
    protected function missingPrivilegesResult(array $privileges): McpToolResult
    {
        return $this->errorResult(\sprintf('Missing privilege: %s', implode(', ', $privileges)), McpToolError::MISSING_PRIVILEGE);
    }

    /**
     * Executes an operation within a transaction that is always rolled back (dry-run preview).
     *
     * The context receives SKIP_TRIGGER_FLOW to prevent flows from firing during the
     * dry-run. Note that with Redis-based delayed cache invalidation, DAL writes may
     * still enqueue invalidations that are not reverted by the DB rollback.
     *
     * A throwable of the operation and a failed rollback become a failure.
     *
     * @param callable(): McpToolResult $operation
     */
    protected function executeWithDryRunResult(Connection $connection, Context $context, callable $operation): McpToolResult
    {
        return $this->rolledBack($connection, $context, $operation, $this->errorResult(...));
    }

    /**
     * Builds the legacy `{"success": true, …}` string.
     *
     * @deprecated tag:v6.8.0 - Will be removed, return successResult() instead. The string still works until then, see the class description.
     *
     * @param array<string, mixed>|list<mixed> $data
     * @param array<string, mixed> $meta
     */
    protected function success(array $data, array $meta = []): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'successResult()'));

        return $this->legacyEnvelope($this->successResult($data, $meta));
    }

    /**
     * Builds the legacy `{"success": false, …}` string.
     *
     * @deprecated tag:v6.8.0 - Will be removed, return errorResult() instead. The string still works until then, see the class description.
     *
     * @param string $code a stable code clients can match on, see {@see McpToolError}; it ends up in
     *                     `structuredContent.error.code` next to `isError: true`
     */
    protected function error(string $message, string $code = McpToolError::TOOL_ERROR): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'errorResult()'));

        return $this->legacyEnvelope($this->errorResult($message, $code));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use invalidCriteriaResult() instead. The string still works until then, see the class description.
     */
    protected function invalidCriteriaError(ShopwareHttpException $e): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'invalidCriteriaResult()'));

        return $this->legacyEnvelope($this->invalidCriteriaResult($e));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use decodeJsonOrErrorResult() instead. The string still works until then, see the class description.
     *
     * @return array<mixed>|string Returns the decoded array on success, or an error JSON string on failure.
     */
    protected function decodeJsonOrError(string $json, string $fieldName = 'input'): array|string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'decodeJsonOrErrorResult()'));

        $result = $this->decodeJsonOrErrorResult($json, $fieldName);

        return $result instanceof McpToolResult ? $this->legacyEnvelope($result) : $result;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use requirePrivilegeResult() instead. The string still works until then, see the class description.
     *
     * @return string|null Error JSON string if a privilege is missing, null if all granted
     */
    protected function requirePrivilege(Context $context, string ...$privileges): ?string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'requirePrivilegeResult()'));

        $result = $this->requirePrivilegeResult($context, ...$privileges);

        return $result !== null ? $this->legacyEnvelope($result) : null;
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use missingPrivilegesResult() instead. The string still works until then, see the class description.
     *
     * @param non-empty-list<string> $privileges
     */
    protected function missingPrivilegesError(array $privileges): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'missingPrivilegesResult()'));

        return $this->legacyEnvelope($this->missingPrivilegesResult($privileges));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use executeWithDryRunResult() instead. The string still works until then, see the class description.
     *
     * @param callable(): string $operation Must return the JSON result string
     */
    protected function executeWithDryRun(Connection $connection, Context $context, callable $operation): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'executeWithDryRunResult()'));

        return $this->rolledBack($connection, $context, $operation, fn (string $message): string => $this->legacyEnvelope($this->errorResult($message)));
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
     * @template T of McpToolResult|string
     *
     * @param callable(): T $operation
     * @param callable(string): T $error
     *
     * @return T
     */
    private function rolledBack(Connection $connection, Context $context, callable $operation, callable $error): McpToolResult|string
    {
        $context->addState(Context::SKIP_TRIGGER_FLOW);

        $connection->beginTransaction();

        try {
            $result = $operation();
        } catch (\Throwable $e) {
            $result = $error($e->getMessage());
        }

        $context->removeState(Context::SKIP_TRIGGER_FLOW);

        try {
            $connection->rollBack();
        } catch (\Throwable $rollbackException) {
            return $error(\sprintf('Dry-run rollback failed: data may have been persisted. %s', $rollbackException->getMessage()));
        }

        return $result;
    }

    /**
     * The legacy envelope as success() and error() built it before they were based on {@see McpToolResult}.
     */
    private function legacyEnvelope(McpToolResult $result): string
    {
        if ($result->error !== null) {
            return Json::encode(['success' => false, 'error' => $result->error->message, 'code' => $result->error->code]);
        }

        $envelope = ['success' => true, 'data' => $result->data];
        if ($result->meta !== []) {
            $envelope['_meta'] = $result->meta;
        }

        return Json::encode($envelope);
    }
}
