<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Loader;

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Tool;
use Mcp\Server\RequestContext;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Mcp\Feature\McpToolConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Registers app-provided MCP tools with the MCP server registry at build time.
 */
#[Package('framework')]
class AppMcpToolLoader extends AbstractAppMcpLoader
{
    /**
     * @internal
     *
     * @param list<string> $allowedTools when non-empty, only these tool names are registered; empty means all allowed
     */
    public function __construct(
        AppFeatureStorage $storage,
        AppMcpCapabilityExecutor $executor,
        LanguageLocaleCodeProvider $localeProvider,
        LoggerInterface $logger,
        private readonly array $allowedTools = [],
    ) {
        parent::__construct($storage, $executor, $localeProvider, $logger);
    }

    protected function fetchRows(): array
    {
        $locale = $this->systemLocale();
        $features = $this->storage->forActiveApps(McpToolConfig::class);

        $rows = [];

        foreach ($features as $feature) {
            $config = $feature->config;

            // external-url tools need a secret to sign the request; internal-url tools (app scripts) do not
            if (!str_starts_with($config->url, '/') && !$feature->appHasSecret) {
                continue;
            }

            $rows[] = [
                ...$config->toArray(),
                'app_name' => $feature->appName,
                'version' => $feature->appVersion,
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
        $toolName = $this->capabilityName($appName, $name);

        if ($this->isReservedName($toolName, $appName, 'tool')) {
            return;
        }

        if ($this->allowedTools !== [] && !\in_array($toolName, $this->allowedTools, true)) {
            return;
        }

        $description = $this->resolveDescription($row, $toolName);
        /** @var array<string, array{type: string, description?: string, required?: bool}>|null $inputSchema */
        $inputSchema = $row['inputSchema'] ?? null;

        $tool = new Tool(
            name: $toolName,
            title: isset($row['label']) && $row['label'] !== '' ? (string) $row['label'] : null,
            inputSchema: $this->buildInputSchema($inputSchema),
            description: $description,
            annotations: null,
        );

        $url = (string) $row['url'];
        $appVersion = (string) ($row['version'] ?? '0.0.0');

        $registry->registerTool($tool, function (RequestContext $context) use ($toolName, $appName, $url, $appVersion): string {
            $request = $context->getRequest();
            $arguments = $request instanceof CallToolRequest ? $request->arguments : [];

            return $this->executor->execute($toolName, $appName, $url, $arguments, $appVersion);
        }, true);
    }

    /**
     * @param array<string, array{type: string, description?: string, required?: bool}>|null $inputSchema
     *
     * @return array{type: 'object', properties: array<string, mixed>, required: list<string>}
     */
    private function buildInputSchema(?array $inputSchema): array
    {
        if ($inputSchema === null) {
            return ['type' => 'object', 'properties' => [], 'required' => []];
        }

        /** @var array<string, mixed> $properties */
        $properties = [];
        /** @var list<string> $required */
        $required = [];

        foreach ($inputSchema as $name => $config) {
            $prop = ['type' => $config['type'] ?? 'string'];

            if (isset($config['description'])) {
                $prop['description'] = $config['description'];
            }

            $properties[(string) $name] = $prop;

            if (($config['required'] ?? false) === true) {
                $required[] = (string) $name;
            }
        }

        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }
}
