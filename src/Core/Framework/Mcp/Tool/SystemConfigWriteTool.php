<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-system-config-write', description: 'Write Shopware system configuration values. Set dryRun=true (default) to preview the change without persisting.')]
#[Package('framework')]
class SystemConfigWriteTool
{
    use McpToolResponse;

    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function __invoke(string $key, string $value, ?string $salesChannelId = null, bool $dryRun = true): string
    {
        $decodedValue = json_decode($value, true);
        $actualValue = json_last_error() === \JSON_ERROR_NONE ? $decodedValue : $value;

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
