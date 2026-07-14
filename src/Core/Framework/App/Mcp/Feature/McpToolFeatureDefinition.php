<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Mcp\Feature;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Feature\AppFeatureConfig;
use Shopware\Core\Framework\App\Feature\AppFeatureDefinition;
use Shopware\Core\Framework\App\Feature\TranslatedString;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Mcp\Mcp;
use Shopware\Core\Framework\App\Mcp\Xml\McpTool;
use Shopware\Core\Framework\App\Validation\Error\MissingPermissionError;
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
    public const TYPE = 'mcp_tool';

    private const FILE = 'Resources/mcp.xml';

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

        $tools = Mcp::createFromXmlFile($appFilesystem->path(self::FILE))->getTools()?->getTools() ?? [];

        $this->validateRequiredPrivileges($manifest, $tools);

        return array_map(
            static function (McpTool $tool) use ($defaultLocale): McpToolConfig {
                // toArray() fills the default locale translation when it is missing,
                // matching what the app expects to be shown for the shop's default language
                $data = $tool->toArray($defaultLocale);
                /** @var array<string, string> $label */
                $label = $data['label'];
                /** @var array<string, string> $description */
                $description = $data['description'];

                return new McpToolConfig(
                    $tool->getName(),
                    $tool->getUrl(),
                    $tool->getRequiredPrivileges(),
                    $tool->getInputSchema(),
                    new TranslatedString($label),
                    new TranslatedString($description),
                );
            },
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

    /**
     * Rejects the install or update when a tool declares required privileges that the
     * manifest does not grant in <permissions>.
     *
     * @param list<McpTool> $tools
     */
    private function validateRequiredPrivileges(Manifest $manifest, array $tools): void
    {
        $permissions = $manifest->getPermissions();
        if ($permissions === null) {
            return;
        }

        $granted = $permissions->asParsedPrivileges();
        $appName = $manifest->getMetadata()->getName();

        foreach ($tools as $tool) {
            $required = $tool->getRequiredPrivileges();
            if ($required === []) {
                continue;
            }

            $missing = array_values(array_filter(
                $required,
                static fn (string $privilege): bool => !\in_array($privilege, $granted, true),
            ));

            if ($missing === []) {
                continue;
            }

            throw AppException::invalidConfiguration(
                $appName,
                new MissingPermissionError(array_map(
                    static fn (string $p): string => \sprintf('Tool "%s" requires "%s" but it is not declared in <permissions>', $tool->getName(), $p),
                    $missing,
                )),
            );
        }
    }
}
