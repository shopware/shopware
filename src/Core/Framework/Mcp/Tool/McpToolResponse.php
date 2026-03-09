<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Provides a unified response envelope for MCP tools.
 *
 * All tools should use success() and error() to build their return values
 * so AI clients receive a predictable JSON structure.
 */
#[Package('framework')]
trait McpToolResponse
{
    private const MAX_RESPONSE_SIZE = 100_000;

    /**
     * @param array<string, mixed>|list<mixed> $data
     * @param array<string, mixed> $meta
     */
    private function success(array $data, array $meta = []): string
    {
        $response = ['success' => true, 'data' => $data];

        if ($meta !== []) {
            $response['_meta'] = $meta;
        }

        $json = json_encode($response, \JSON_THROW_ON_ERROR);

        if (\strlen($json) > self::MAX_RESPONSE_SIZE) {
            $meta['truncated'] = true;
            $meta['truncatedMessage'] = 'Response exceeded size limit. Use "includes" in criteria to select specific fields, or reduce "limit".';

            if (array_is_list($data)) {
                $data = \array_slice($data, 0, 5);
            }

            $response = ['success' => true, 'data' => $data, '_meta' => $meta];
            $json = json_encode($response, \JSON_THROW_ON_ERROR);

            if (\strlen($json) > self::MAX_RESPONSE_SIZE) {
                $response['data'] = [];
                $response['_meta']['truncatedMessage'] = 'Response still too large after truncation. Data cleared. Use "includes" to select specific fields.';
                $json = json_encode($response, \JSON_THROW_ON_ERROR);
            }
        }

        return $json;
    }

    private function error(string $message): string
    {
        return json_encode(['success' => false, 'error' => $message], \JSON_THROW_ON_ERROR);
    }

    /**
     * @return string|null Error JSON string if a privilege is missing, null if all granted
     */
    private function requirePrivilege(Context $context, string ...$privileges): ?string
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
     * @param callable(): string $operation Must return the JSON result string
     */
    private function executeWithDryRun(Connection $connection, callable $operation): string
    {
        $connection->beginTransaction();

        try {
            return $operation();
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        } finally {
            try {
                $connection->rollBack();
            } catch (\Throwable) {
            }
        }
    }

    /**
     * @return list<array{entity: string, ids: list<string>, operation: string}>
     */
    private function formatWriteEvents(EntityWrittenContainerEvent $events, string $operation): array
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
}
