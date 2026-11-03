<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Loader;

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Tool;
use Mcp\Server\RequestContext;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Mcp\Feature\McpToolConfig;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Registers app-provided MCP tools with the MCP server registry at build time.
 *
 * @extends AbstractAppMcpLoader<McpToolConfig>
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

    protected function getConfigClass(): string
    {
        return McpToolConfig::class;
    }

    protected function registerCapability(RegistryInterface $registry, AppFeature $feature, string $locale): void
    {
        if (!$feature->appHasSecret && !str_starts_with($feature->config->url, '/')) {
            return;
        }

        $appName = $feature->appName;
        $config = $feature->config;
        $toolName = $this->capabilityName($appName, $config->name);

        if ($this->isReservedName($toolName, $appName, 'tool')) {
            return;
        }

        if ($this->allowedTools !== [] && !\in_array($toolName, $this->allowedTools, true)) {
            return;
        }

        $label = $config->label->forLocale($locale);

        $tool = new Tool(
            name: $toolName,
            title: $label ?: null,
            inputSchema: $this->buildInputSchema($config->inputSchema),
            description: $this->resolveDescription($config->description->forLocale($locale), $label, $toolName),
            annotations: null,
        );

        $url = $config->url;
        $appVersion = $feature->appVersion;

        $registry->registerTool($tool, function (RequestContext $context) use ($toolName, $appName, $url, $appVersion): string {
            $request = $context->getRequest();
            $arguments = $request instanceof CallToolRequest ? $request->arguments : [];

            return $this->executor->execute($toolName, $appName, $url, $arguments, $appVersion);
        });
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
