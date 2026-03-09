<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-system-config-read', description: 'Read Shopware system configuration values. Pass a domain prefix (e.g. "core.listing") to get all keys under it, or a full key (e.g. "core.listing.defaultSorting") for a single value. Optionally scope to a sales channel. Use this before shopware-system-config-write to check current values.')]
#[Package('framework')]
class SystemConfigReadTool
{
    use McpToolResponse;

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
