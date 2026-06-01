<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Wraps a decoded JSON-RPC response body and exposes typed mutation methods.
 *
 * Internally uses \stdClass (json_decode with associative=false) to preserve
 * JSON object semantics during encode — empty {} stays {} not [].
 *
 * @internal
 */
#[Package('framework')]
class McpJsonRpcResponse
{
    private function __construct(private readonly \stdClass $data)
    {
    }

    public static function fromJson(string $json): ?self
    {
        try {
            $decoded = json_decode($json, false, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return $decoded instanceof \stdClass ? new self($decoded) : null;
    }

    /**
     * @param list<string> $allowlist
     */
    public function filterTools(array $allowlist): void
    {
        $this->filterResultList(
            'tools',
            static fn (\stdClass $item): bool => \in_array($item->name ?? '', $allowlist, true),
        );
    }

    /**
     * @param list<string> $allowlist
     */
    public function filterResources(array $allowlist): void
    {
        $this->filterResultList(
            'resources',
            static fn (\stdClass $item): bool => \in_array($item->uri ?? '', $allowlist, true),
        );
    }

    /**
     * @param list<string> $allowlist
     */
    public function filterPrompts(array $allowlist): void
    {
        $this->filterResultList(
            'prompts',
            static fn (\stdClass $item): bool => \in_array($item->name ?? '', $allowlist, true),
        );
    }

    /**
     * Sets result._meta.shopware.user/integration from the given IDs.
     * Returns true when metadata was added, false when both IDs are null.
     */
    public function addShopwareMeta(?string $userId, ?string $integrationId): bool
    {
        if ($userId === null && $integrationId === null) {
            return false;
        }

        $result = $this->data->result ?? null;
        if (!$result instanceof \stdClass) {
            return false;
        }

        if (!isset($result->_meta) || !$result->_meta instanceof \stdClass) {
            $result->_meta = new \stdClass();
        }

        if (!isset($result->_meta->shopware) || !$result->_meta->shopware instanceof \stdClass) {
            $result->_meta->shopware = new \stdClass();
        }

        if ($userId !== null) {
            $result->_meta->shopware->user = (object) ['id' => $userId];
        }

        if ($integrationId !== null) {
            $result->_meta->shopware->integration = (object) ['id' => $integrationId];
        }

        return true;
    }

    public function encode(): string
    {
        return Json::encode($this->data);
    }

    private function filterResultList(string $key, \Closure $predicate): void
    {
        $result = $this->data->result ?? null;
        if (!$result instanceof \stdClass) {
            return;
        }

        $items = $result->{$key} ?? null;
        if (!\is_array($items)) {
            return;
        }

        $result->{$key} = array_values(
            array_filter(
                $items,
                static fn (mixed $item): bool => $item instanceof \stdClass && $predicate($item),
            ),
        );
    }
}
