<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Loader;

use Doctrine\DBAL\Connection;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Tool;
use Mcp\Server\RequestContext;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Loads app-provided MCP tools from the database and registers them
 * with the MCP server registry at build time.
 */
#[Package('framework')]
class AppMcpToolLoader extends AbstractAppMcpLoader
{
    /**
     * @internal
     *
     * @param list<string> $allowedTools When non-empty, only these tool names are registered. Empty = all allowed.
     */
    public function __construct(
        Connection $connection,
        AppMcpCapabilityExecutor $executor,
        private readonly array $allowedTools = [],
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($connection, $executor, $logger);
    }

    protected function fetchRows(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT
                t.name,
                t.url,
                t.input_schema,
                a.name AS app_name,
                a.app_secret,
                a.version,
                tt.label,
                tt.description
            FROM app_mcp_tool t
            INNER JOIN app a ON t.app_id = a.id AND a.active = 1
            LEFT JOIN app_mcp_tool_translation tt
                ON t.id = tt.app_mcp_tool_id
                AND tt.language_id = UNHEX(:languageId)
            WHERE (a.app_secret IS NOT NULL OR t.url LIKE \'/%\')
            ORDER BY a.name, t.name',
            ['languageId' => Defaults::LANGUAGE_SYSTEM],
        );
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
        $inputSchema = $this->buildInputSchema(isset($row['input_schema']) ? (string) $row['input_schema'] : null);

        $tool = new Tool(
            name: $toolName,
            title: isset($row['label']) && $row['label'] !== '' ? (string) $row['label'] : null,
            inputSchema: $inputSchema,
            description: $description,
            annotations: null,
        );

        $appSecret = isset($row['app_secret']) ? (string) $row['app_secret'] : null;
        $url = (string) $row['url'];
        $appVersion = (string) ($row['version'] ?? '0.0.0');

        $registry->registerTool($tool, function (RequestContext $context) use ($toolName, $appSecret, $url, $appVersion): string {
            $request = $context->getRequest();
            $arguments = $request instanceof CallToolRequest ? $request->arguments : [];

            return $this->executor->execute($toolName, $appSecret, $url, $arguments, $appVersion);
        }, true);
    }

    /**
     * @return array{type: 'object', properties: array<string, mixed>, required: list<string>}
     */
    private function buildInputSchema(?string $inputSchemaJson): array
    {
        if ($inputSchemaJson === null) {
            return ['type' => 'object', 'properties' => [], 'required' => []];
        }

        $schema = json_decode($inputSchemaJson, true);

        if (!\is_array($schema)) {
            return ['type' => 'object', 'properties' => [], 'required' => []];
        }

        /** @var array<string, mixed> $properties */
        $properties = [];
        /** @var list<string> $required */
        $required = [];

        foreach ($schema as $name => $config) {
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
