<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Mcp\Feature;

use Shopware\Core\Framework\App\Feature\AppFeatureConfig;
use Shopware\Core\Framework\App\Feature\AppFeatureDefinition;
use Shopware\Core\Framework\App\Feature\TranslatedString;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\App\Mcp\Xml\McpResource;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 *
 * @implements AppFeatureDefinition<McpResourceConfig>
 *
 * @phpstan-import-type McpResourcePayload from McpResourceConfig
 */
#[Package('framework')]
class McpResourceFeatureDefinition implements AppFeatureDefinition
{
    public const TYPE = 'mcp_resource';

    private const FILE = 'Resources/mcp.xml';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getConfigClass(): string
    {
        return McpResourceConfig::class;
    }

    public function fromApp(Manifest $manifest, Filesystem $appFilesystem, string $defaultLocale): array
    {
        if (!$appFilesystem->has(self::FILE)) {
            return [];
        }

        $resources = Mcp::createFromXmlFile($appFilesystem->path(self::FILE))->getResources()?->getResources() ?? [];

        return array_map(
            static function (McpResource $resource) use ($defaultLocale): McpResourceConfig {
                // toArray() fills the default locale translation when it is missing,
                // matching what the app expects to be shown for the shop's default language
                $data = $resource->toArray($defaultLocale);
                /** @var array<string, string> $label */
                $label = $data['label'];
                /** @var array<string, string> $description */
                $description = $data['description'];

                return new McpResourceConfig(
                    $resource->getName(),
                    $resource->getUri(),
                    $resource->getUrl(),
                    $resource->getMimeType(),
                    new TranslatedString($label),
                    new TranslatedString($description),
                );
            },
            $resources,
        );
    }

    /**
     * @return McpResourcePayload
     */
    public function toPayload(AppFeatureConfig $declared, ?AppFeatureConfig $stored): array
    {
        return $declared->toArray();
    }

    /**
     * @param McpResourcePayload $payload
     */
    public function fromPayload(array $payload): McpResourceConfig
    {
        return new McpResourceConfig(
            $payload['name'],
            $payload['uri'],
            $payload['url'],
            $payload['mimeType'] ?? null,
            new TranslatedString($payload['label'] ?? []),
            new TranslatedString($payload['description'] ?? []),
        );
    }
}
