<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp;

use Mcp\Exception\InvalidArgumentException;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Implementation;
use Mcp\Schema\JsonRpc\ResultInterface;
use Mcp\Schema\Prompt;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\Result\InitializeResult;
use Mcp\Schema\Result\ListPromptsResult;
use Mcp\Schema\Result\ListResourcesResult;
use Mcp\Schema\Result\ListToolsResult;
use Mcp\Schema\ServerCapabilities;
use Mcp\Schema\Tool;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Typed DTO for a JSON-RPC response body received from the MCP SDK.
 *
 * Decodes the response using the SDK's own typed result classes so that
 * serialization — including empty JSON objects like inputSchema.properties: {} —
 * is handled correctly by each result's JsonSerializable implementation.
 *
 * @internal
 */
#[Package('framework')]
class McpJsonRpcResponse implements \JsonSerializable
{
    private function __construct(
        private readonly string|int $id,
        private readonly string $jsonrpc,
        private ResultInterface $result,
    ) {
    }

    public static function fromJson(string $json): ?self
    {
        try {
            $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($data)) {
            return null;
        }

        $result = self::parseResult($data['result'] ?? null);

        if ($result === null) {
            return null;
        }

        return new self($data['id'] ?? '', $data['jsonrpc'] ?? '2.0', $result);
    }

    /**
     * @param list<string> $allowlist
     */
    public function filterTools(array $allowlist): void
    {
        if (!$this->result instanceof ListToolsResult) {
            return;
        }

        $filtered = array_values(
            array_filter(
                $this->result->tools,
                static fn (Tool $tool): bool => \in_array($tool->name, $allowlist, true),
            ),
        );

        $this->result = new ListToolsResult($filtered, $this->result->nextCursor);
    }

    /**
     * @param list<string> $allowlist
     */
    public function filterResources(array $allowlist): void
    {
        if (!$this->result instanceof ListResourcesResult) {
            return;
        }

        $filtered = array_values(
            array_filter(
                $this->result->resources,
                static fn (ResourceDefinition $resource): bool => \in_array($resource->uri, $allowlist, true),
            ),
        );

        $this->result = new ListResourcesResult($filtered, $this->result->nextCursor);
    }

    /**
     * @param list<string> $allowlist
     */
    public function filterPrompts(array $allowlist): void
    {
        if (!$this->result instanceof ListPromptsResult) {
            return;
        }

        $filtered = array_values(
            array_filter(
                $this->result->prompts,
                static fn (Prompt $prompt): bool => \in_array($prompt->name, $allowlist, true),
            ),
        );

        $this->result = new ListPromptsResult($filtered, $this->result->nextCursor);
    }

    /**
     * Sets result._meta.shopware.user/integration from the given IDs.
     * Returns true when metadata was added, false when both IDs are null.
     */
    public function addShopwareMeta(?string $userId, ?string $integrationId): bool
    {
        if (!$this->result instanceof InitializeResult) {
            return false;
        }

        if ($userId === null && $integrationId === null) {
            return false;
        }

        $shopware = [];
        if ($userId !== null) {
            $shopware['user'] = ['id' => $userId];
        }
        if ($integrationId !== null) {
            $shopware['integration'] = ['id' => $integrationId];
        }

        $this->result = new InitializeResult(
            $this->result->capabilities,
            $this->result->serverInfo,
            $this->result->instructions,
            array_merge($this->result->meta ?? [], ['shopware' => $shopware]),
            $this->result->protocolVersion,
        );

        return true;
    }

    /**
     * @return array{jsonrpc: string, id: string|int, result: ResultInterface}
     */
    public function jsonSerialize(): array
    {
        return [
            'jsonrpc' => $this->jsonrpc,
            'id' => $this->id,
            'result' => $this->result,
        ];
    }

    private static function parseResult(mixed $resultData): ?ResultInterface
    {
        if (!\is_array($resultData)) {
            return null;
        }

        try {
            if (\array_key_exists('tools', $resultData)) {
                /** @phpstan-ignore argument.type (To fix this issue, the response array would need to be validated to contain only allowed values) */
                return ListToolsResult::fromArray($resultData);
            }
            if (\array_key_exists('resources', $resultData)) {
                /** @phpstan-ignore argument.type (To fix this issue, the response array would need to be validated to contain only allowed values) */
                return ListResourcesResult::fromArray($resultData);
            }
            if (\array_key_exists('prompts', $resultData)) {
                /** @phpstan-ignore argument.type (To fix this issue, the response array would need to be validated to contain only allowed values) */
                return ListPromptsResult::fromArray($resultData);
            }
            if (\array_key_exists('capabilities', $resultData)) {
                $protocolVersion = $resultData['protocolVersion'] ?? null;
                $capabilitiesData = $resultData['capabilities'];
                $serverInfoData = $resultData['serverInfo'] ?? null;

                if (!\is_string($protocolVersion) || !\is_array($capabilitiesData) || !\is_array($serverInfoData)) {
                    return null;
                }

                $serverName = $serverInfoData['name'] ?? null;
                $serverVersion = $serverInfoData['version'] ?? null;

                if (!\is_string($serverName) || !\is_string($serverVersion)) {
                    return null;
                }

                return new InitializeResult(
                    /** @phpstan-ignore argument.type (To fix this issue, the response array would need to be validated to contain only allowed values) */
                    capabilities: ServerCapabilities::fromArray($capabilitiesData),
                    serverInfo: new Implementation(name: $serverName, version: $serverVersion),
                    instructions: \is_string($resultData['instructions'] ?? null) ? $resultData['instructions'] : null,
                    meta: \is_array($resultData['_meta'] ?? null) ? $resultData['_meta'] : null,
                    protocolVersion: ProtocolVersion::tryFrom($protocolVersion),
                );
            }
        } catch (InvalidArgumentException) {
            return null;
        }

        return null;
    }
}
