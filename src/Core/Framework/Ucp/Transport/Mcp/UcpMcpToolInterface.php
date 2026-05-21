<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Mcp;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Contract every UCP MCP tool implements. The dispatcher resolves a tool by
 * its name, validates the active capability, then calls `invoke`.
 */
#[Package('framework')]
interface UcpMcpToolInterface
{
    /**
     * @param array<string, mixed> $arguments parsed JSON-RPC params.arguments
     *
     * @return array<string, mixed> structured-content payload (mirrors REST response)
     */
    public function invoke(array $arguments, UcpRequestContext $context): array;

    /**
     * @return array<string, mixed> JSON-Schema for `inputSchema` in tools/list response
     */
    public function inputSchema(): array;

    /**
     * @return array<string, mixed>|null JSON-Schema for `outputSchema`, or null to omit
     */
    public function outputSchema(): ?array;
}
