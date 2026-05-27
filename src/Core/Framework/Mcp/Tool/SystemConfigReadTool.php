<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-system-config-read', title: 'System Config Read', description: 'Read Shopware application configuration values in the core.* namespace. Pass a domain prefix (e.g. "core.listing") to get all keys, or a full dotted key to read a single value. For theme appearance settings (colors, logos, fonts), use shopware-theme-config instead. Optionally scope to a sales channel.')]
#[McpToolRequires('system_config:read')]
#[Package('framework')]
class SystemConfigReadTool extends McpToolResponse
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(string $key, ?string $salesChannelId = null): string
    {
        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilege($context, 'system_config:read')) {
            return $error;
        }

        if (str_contains($key, '.') && substr_count($key, '.') >= 2) {
            $value = $this->systemConfigService->get($key, $salesChannelId);

            return $this->success(['key' => $key, 'value' => $value]);
        }

        $domain = $this->systemConfigService->getDomain($key, $salesChannelId);

        return $this->success(['domain' => $key, 'values' => $domain]);
    }
}
