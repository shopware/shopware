<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Mcp\Feature;

use Shopware\Core\Framework\App\Feature\AppFeatureConfig;
use Shopware\Core\Framework\App\Feature\AppFeatureDefinition;
use Shopware\Core\Framework\App\Feature\TranslatedString;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 *
 * @extends AppFeatureDefinition<McpResourceConfig>
 *
 * @phpstan-import-type McpResourcePayload from McpResourceConfig
 */
#[Package('framework')]
class McpResourceFeatureDefinition extends AppFeatureDefinition
{
    public const TYPE = 'mcp_resource';

    private const FILE = 'Resources/mcp.xml';

    private const XSD_FILE = __DIR__ . '/../Schema/mcp-1.0.xsd';

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

        $doc = XmlParserUtils::loadFile($appFilesystem->path(self::FILE), self::XSD_FILE);

        $configs = [];
        foreach ($doc->getElementsByTagName('mcp-resource') as $element) {
            $configs[] = new McpResourceConfig(
                $element->getAttribute('name'),
                $element->getAttribute('uri'),
                $element->getAttribute('url'),
                $element->hasAttribute('mime-type') ? $element->getAttribute('mime-type') : null,
                new TranslatedString(XmlParserUtils::ensureTranslationForLocale(
                    XmlParserUtils::parseTranslations($element, 'label'),
                    $defaultLocale,
                )),
                new TranslatedString(XmlParserUtils::ensureTranslationForLocale(
                    XmlParserUtils::parseTranslations($element, 'description'),
                    $defaultLocale,
                )),
            );
        }

        return $configs;
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
