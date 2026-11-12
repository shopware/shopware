<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\ToolAnnotations;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Result\McpToolResult;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpTool(
    name: 'shopware-system-config-read',
    title: 'System Config Read',
    annotations: new ToolAnnotations(readOnlyHint: true, openWorldHint: false),
    description: 'Read Shopware application configuration values in the core.* namespace. Pass a domain prefix (e.g. "core.listing") to get all keys, or a full dotted key to read a single value. For theme appearance settings (colors, logos, fonts), use shopware-theme-config instead. Optionally scope to a sales channel.'
)]
#[McpToolGroup('system-config', description: 'Read and change shop settings in the core.* system configuration, for all sales channels or one. Theme colors, logos and fonts are in the theme toolset.')]
#[McpToolRequires('system_config:read')]
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

    public function __invoke(string $key, ?string $salesChannelId = null): McpToolResult
    {
        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilegeResult($context, 'system_config:read')) {
            return $error;
        }

        if (str_contains($key, '.') && substr_count($key, '.') >= 2) {
            $value = $this->systemConfigService->get($key, $salesChannelId);

            return $this->successResult(['key' => $key, 'value' => $value]);
        }

        $domain = $this->systemConfigService->getDomain($key, $salesChannelId);

        return $this->successResult(['domain' => $key, 'values' => $domain]);
    }
}
