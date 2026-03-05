<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Loader;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Mcp\Capability\Registry\Loader\LoaderInterface;
use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Tool;
use Mcp\Server\RequestContext;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Loads app-provided MCP tools from the database and registers them
 * with the MCP server registry at build time.
 */
#[Package('framework')]
class AppMcpToolLoader implements LoaderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly AppMcpToolExecutor $executor,
    ) {
    }

    public function load(RegistryInterface $registry): void
    {
        try {
            $tools = $this->loadActiveAppTools();
        } catch (DBALException) {
            return;
        }

        foreach ($tools as $toolData) {
            $appName = (string) $toolData['app_name'];
            $name = (string) $toolData['name'];
            $toolName = $appName . '-' . $name;
            $description = (string) ($toolData['label'] ?? $toolData['description'] ?? $toolName);
            $inputSchema = $this->buildInputSchema(isset($toolData['input_schema']) ? (string) $toolData['input_schema'] : null);

            $tool = new Tool(
                name: $toolName,
                inputSchema: $inputSchema,
                description: $description,
                annotations: null,
            );

            $appSecret = (string) $toolData['app_secret'];
            $url = (string) $toolData['url'];

            $registry->registerTool($tool, function (RequestContext $context) use ($toolName, $appSecret, $url): string {
                $request = $context->getRequest();
                $arguments = $request instanceof CallToolRequest ? $request->arguments : [];

                return $this->executor->execute($toolName, $appSecret, $url, $arguments);
            }, true);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadActiveAppTools(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT
                t.name,
                t.url,
                t.input_schema,
                a.name AS app_name,
                a.app_secret,
                COALESCE(tt_locale.label, tt_default.label) AS label,
                COALESCE(tt_locale.description, tt_default.description) AS description
            FROM app_mcp_tool t
            INNER JOIN app a ON t.app_id = a.id AND a.active = 1
            LEFT JOIN app_mcp_tool_translation tt_locale
                ON t.id = tt_locale.app_mcp_tool_id
                AND tt_locale.language_id = (
                    SELECT l.id FROM `language` l
                    INNER JOIN locale lo ON l.locale_id = lo.id AND lo.code = :locale
                    LIMIT 1
                )
            LEFT JOIN app_mcp_tool_translation tt_default
                ON t.id = tt_default.app_mcp_tool_id
                AND tt_default.language_id = (
                    SELECT l2.id FROM `language` l2
                    INNER JOIN locale lo2 ON l2.locale_id = lo2.id AND lo2.code = :fallback
                    LIMIT 1
                )
            WHERE a.app_secret IS NOT NULL
            ORDER BY a.name, t.name',
            ['locale' => 'en-GB', 'fallback' => 'en-GB'],
        );
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

            if (!empty($config['required'])) {
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
