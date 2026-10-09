<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Mcp\Feature;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Feature\AppFeatureConfig;
use Shopware\Core\Framework\App\Feature\AppFeatureDefinition;
use Shopware\Core\Framework\App\Feature\TranslatedString;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 *
 * @extends AppFeatureDefinition<McpToolConfig>
 *
 * @phpstan-import-type McpToolPayload from McpToolConfig
 */
#[Package('framework')]
class McpToolFeatureDefinition extends AppFeatureDefinition
{
    public const TYPE = 'mcp_tool';

    private const FILE = 'Resources/mcp.xml';

    private const XSD_FILE = __DIR__ . '/../Schema/mcp-1.0.xsd';

    public function getType(): string
    {
        return self::TYPE;
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

        $doc = XmlParserUtils::loadFile($appFilesystem->path(self::FILE), self::XSD_FILE);

        $configs = [];
        foreach ($doc->getElementsByTagName('mcp-tool') as $element) {
            $configs[] = new McpToolConfig(
                $element->getAttribute('name'),
                $element->getAttribute('url'),
                self::parseRequiredPrivileges($element),
                self::parseInputSchema($element),
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

    /**
     * Rejects the install or update when a tool declares required privileges that the
     * manifest does not grant in <permissions>.
     *
     * @param list<McpToolConfig> $configs
     */
    public function validate(array $configs, AppPersistContext $context): void
    {
        $permissions = $context->manifest->getPermissions();
        if ($permissions === null) {
            return;
        }

        $granted = $permissions->asParsedPrivileges();

        foreach ($configs as $config) {
            $missing = array_values(array_diff($config->requiredPrivileges, $granted));

            if ($missing === []) {
                continue;
            }

            throw AppException::invalidConfiguration(
                $context->manifest->getMetadata()->getName(),
                new MissingPermissionError(array_map(
                    static fn (string $p): string => \sprintf('Tool "%s" requires "%s" but it is not declared in <permissions>', $config->name, $p),
                    $missing,
                )),
            );
        }
    }

    /**
     * @return list<string>
     */
    private static function parseRequiredPrivileges(\DOMElement $tool): array
    {
        $privileges = [];
        foreach ($tool->getElementsByTagName('privilege') as $privilege) {
            $text = trim($privilege->textContent);
            if ($text !== '') {
                $privileges[] = $text;
            }
        }

        return $privileges;
    }

    /**
     * @return array<string, array{type: string, description?: string, required?: bool}>|null
     */
    private static function parseInputSchema(\DOMElement $tool): ?array
    {
        if ($tool->getElementsByTagName('input-schema')->length === 0) {
            return null;
        }

        $properties = [];
        foreach ($tool->getElementsByTagName('property') as $property) {
            $name = $property->getAttribute('name');
            if ($name === '') {
                continue;
            }

            $entry = ['type' => $property->getAttribute('type') ?: 'string'];

            if ($property->hasAttribute('description')) {
                $entry['description'] = $property->getAttribute('description');
            }

            if ($property->hasAttribute('required')) {
                $entry['required'] = $property->getAttribute('required') === 'true';
            }

            $properties[$name] = $entry;
        }

        return $properties;
    }
}
