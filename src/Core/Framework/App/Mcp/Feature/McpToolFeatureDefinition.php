<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Mcp\Feature;

use Shopware\Core\Framework\App\Feature\AppFeatureConfig;
use Shopware\Core\Framework\App\Feature\AppFeatureDefinition;
use Shopware\Core\Framework\App\Feature\TranslatedString;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\App\Mcp\Xml\McpTool;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 *
 * @implements AppFeatureDefinition<McpToolConfig>
 *
 * @phpstan-import-type McpToolPayload from McpToolConfig
 */
#[Package('framework')]
class McpToolFeatureDefinition implements AppFeatureDefinition
{
    private const FILE = 'Resources/mcp.xml';

    public function getType(): string
    {
        return 'mcp_tool';
    }

    public function getConfigClass(): string
    {
        return McpToolConfig::class;
    }

    public function fromApp(Manifest $manifest, Filesystem $appFilesystem, string $defaultLocale): array
    {
        if (!$appFilesystem->has(self::FILE)) {
            return [];
        }

        $tools = Mcp::createFromXmlFile($appFilesystem->path(self::FILE))->getTools()?->getTools() ?? [];

        return array_map(
            static fn (McpTool $tool): McpToolConfig => new McpToolConfig(
                $tool->getName(),
                $tool->getUrl(),
                $tool->getRequiredPrivileges(),
                $tool->getInputSchema(),
                new TranslatedString($tool->getLabel()),
                new TranslatedString($tool->getDescription()),
            ),
            $tools,
        );
    }

    /**
     * @return McpToolPayload
     */
    public function toPayload(AppFeatureConfig $declared, ?AppFeatureConfig $stored): array
    {
        return $declared->toArray();
    }

    /**
     * @param McpToolPayload $payload
     */
    public function fromPayload(array $payload): McpToolConfig
    {
        return new McpToolConfig(
            $payload['name'],
            $payload['url'],
            $payload['requiredPrivileges'] ?? [],
            $payload['inputSchema'] ?? null,
            new TranslatedString($payload['label'] ?? []),
            new TranslatedString($payload['description'] ?? []),
        );
    }
}
