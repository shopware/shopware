<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Loader;

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Content\BlobResourceContents;
use Mcp\Schema\Content\TextResourceContents;
use Mcp\Schema\ResourceDefinition;
use Mcp\Server\RequestContext;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Mcp\Feature\McpResourceConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpException;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Registers app-provided MCP resources with the MCP server registry at build time.
 *
 * The app responds with a JSON object holding the content (`{"uri": "...", "mimeType": "...", "text": "..."}`, or `blob` for base64 data).
 * The handler decodes it into resource contents, because the MCP SDK would otherwise send the whole body as the resource text.
 *
 * @extends AbstractAppMcpLoader<McpResourceConfig>
 */
#[Package('framework')]
class AppMcpResourceLoader extends AbstractAppMcpLoader
{
    protected function getConfigClass(): string
    {
        return McpResourceConfig::class;
    }

    protected function registerCapability(RegistryInterface $registry, AppFeature $feature, string $locale): void
    {
        if (!$feature->appHasSecret) {
            return;
        }

        $appName = $feature->appName;
        $config = $feature->config;
        $resourceName = $this->capabilityName($appName, $config->name);

        if ($this->isReservedName($resourceName, $appName, 'resource')) {
            return;
        }

        $resource = new ResourceDefinition(
            uri: $config->uri,
            name: $resourceName,
            description: $this->resolveDescription(
                $config->description->forLocale($locale),
                $config->label->forLocale($locale),
                $resourceName,
            ),
            mimeType: $config->mimeType,
        );

        $url = $config->url;
        $uri = $config->uri;

        $mimeType = $config->mimeType;

        $registry->registerResource($resource, function (RequestContext $context) use ($resourceName, $appName, $url, $uri, $mimeType): TextResourceContents|BlobResourceContents {
            return $this->decodeContents(
                $resourceName,
                $uri,
                $mimeType,
                $this->executor->execute($resourceName, $appName, $url, ['uri' => $uri]),
            );
        });
    }

    private function decodeContents(string $resourceName, string $uri, ?string $mimeType, string $body): TextResourceContents|BlobResourceContents
    {
        try {
            $decoded = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw McpException::appResourceResponseNotJson($resourceName);
        }

        if (!\is_array($decoded) || array_is_list($decoded)) {
            throw McpException::appResourceResponseInvalid($resourceName);
        }

        if (($decoded['success'] ?? null) === false) {
            $error = $decoded['error'] ?? null;

            throw McpException::appResourceFailed($resourceName, \is_string($error) ? $error : null);
        }

        $responseMimeType = $decoded['mimeType'] ?? null;
        $mimeType = \is_string($responseMimeType) && $responseMimeType !== '' ? $responseMimeType : $mimeType;

        if (\is_string($decoded['text'] ?? null)) {
            return new TextResourceContents($uri, $mimeType, $decoded['text']);
        }

        if (\is_string($decoded['blob'] ?? null)) {
            return new BlobResourceContents($uri, $mimeType, $decoded['blob']);
        }

        throw McpException::appResourceResponseInvalid($resourceName);
    }
}
