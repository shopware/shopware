<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

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
        }

        return $json;
    }

    private function error(string $message): string
    {
        return json_encode(['success' => false, 'error' => $message], \JSON_THROW_ON_ERROR);
    }
}
