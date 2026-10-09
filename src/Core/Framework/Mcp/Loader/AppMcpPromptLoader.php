<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Loader;

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Prompt;
use Mcp\Server\RequestContext;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Mcp\Feature\McpPromptConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Registers app-provided MCP prompts with the MCP server registry at build time.
 *
 * @extends AbstractAppMcpLoader<McpPromptConfig>
 */
#[Package('framework')]
class AppMcpPromptLoader extends AbstractAppMcpLoader
{
    protected function getConfigClass(): string
    {
        return McpPromptConfig::class;
    }

    protected function registerCapability(RegistryInterface $registry, AppFeature $feature, string $locale): void
    {
        if (!$feature->appHasSecret) {
            return;
        }

        $appName = $feature->appName;
        $config = $feature->config;
        $promptName = $this->capabilityName($appName, $config->name);

        if ($this->isReservedName($promptName, $appName, 'prompt')) {
            return;
        }

        $label = $config->label->forLocale($locale);

        $prompt = new Prompt(
            name: $promptName,
            title: $label ?: null,
            description: $this->resolveDescription($config->description->forLocale($locale), $label, $promptName),
        );

        $url = $config->url;

        $registry->registerPrompt($prompt, function (RequestContext $context) use ($promptName, $appName, $url): string {
            return $this->executor->execute($promptName, $appName, $url, []);
        }, []);
    }
}
