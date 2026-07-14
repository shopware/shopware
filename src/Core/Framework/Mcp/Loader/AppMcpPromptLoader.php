<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Loader;

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Prompt;
use Mcp\Server\RequestContext;
use Shopware\Core\Framework\App\Mcp\Feature\McpPromptConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Registers app-provided MCP prompts with the MCP server registry at build time.
 */
#[Package('framework')]
class AppMcpPromptLoader extends AbstractAppMcpLoader
{
    protected function fetchRows(): array
    {
        $locale = $this->systemLocale();
        $features = $this->storage->forActiveApps(McpPromptConfig::class);

        $rows = [];

        foreach ($features as $feature) {
            if (!$feature->appHasSecret) {
                continue;
            }

            $config = $feature->config;

            $rows[] = [
                ...$config->toArray(),
                'app_name' => $feature->appName,
                'label' => $config->label->forLocale($locale),
                'description' => $config->description->forLocale($locale),
            ];
        }

        return $rows;
    }

    protected function registerCapability(RegistryInterface $registry, array $row): void
    {
        $appName = (string) $row['app_name'];
        $name = (string) $row['name'];
        $promptName = $this->capabilityName($appName, $name);

        if ($this->isReservedName($promptName, $appName, 'prompt')) {
            return;
        }

        $description = $this->resolveDescription($row, $promptName);

        $prompt = new Prompt(
            name: $promptName,
            title: isset($row['label']) && $row['label'] !== '' ? (string) $row['label'] : null,
            description: $description,
        );

        $url = (string) $row['url'];

        $registry->registerPrompt($prompt, function (RequestContext $context) use ($promptName, $appName, $url): string {
            return $this->executor->execute($promptName, $appName, $url, []);
        }, [], true);
    }
}
