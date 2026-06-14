<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolDependsOn;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-system-config-write', title: 'System Config Write', description: 'Modify or overwrite a Shopware system configuration value — use this whenever the user wants to change, set, or update a config key. Provide the full dotted key (e.g. \'core.basicInformation.shopName\') and the new value. dryRun=true (default) shows a before/after diff without saving; set dryRun=false to persist. Optionally scope to a sales channel.')]
#[McpToolDependsOn('shopware-system-config-read')]
#[McpToolRequires('system_config:update')]
#[Package('framework')]
class SystemConfigWriteTool extends McpToolResponse
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(string $key, string $value, ?string $salesChannelId = null, bool $dryRun = true): string
    {
        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilege($context, 'system_config:update')) {
            return $error;
        }

        $decodedValue = json_decode($value, true);
        $actualValue = json_last_error() === \JSON_ERROR_NONE ? $decodedValue : $value;

        if ($actualValue === null) {
            return $this->error('Setting null is not supported via MCP as it would delete the config entry. Use the Admin API to delete configuration values.');
        }

        $oldValue = $this->systemConfigService->get($key, $salesChannelId);

        if ($dryRun) {
            return $this->success([
                'key' => $key,
                'oldValue' => $oldValue,
                'newValue' => $actualValue,
            ], ['dryRun' => true, 'salesChannelId' => $salesChannelId]);
        }

        $this->systemConfigService->set($key, $actualValue, $salesChannelId);

        return $this->success([
            'key' => $key,
            'oldValue' => $oldValue,
            'newValue' => $actualValue,
        ], ['dryRun' => false, 'salesChannelId' => $salesChannelId]);
    }
}
