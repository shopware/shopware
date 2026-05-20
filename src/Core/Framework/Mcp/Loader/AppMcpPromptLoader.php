<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Loader;

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Prompt;
use Mcp\Server\RequestContext;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 *
 * Loads app-provided MCP prompts from the database and registers them
 * with the MCP server registry at build time.
 */
#[Package('framework')]
class AppMcpPromptLoader extends AbstractAppMcpLoader
{
    protected function fetchRows(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT
                p.name,
                p.url,
                a.name AS app_name,
                a.app_secret,
                pt.label,
                pt.description
            FROM app_mcp_prompt p
            INNER JOIN app a ON p.app_id = a.id AND a.active = 1
            LEFT JOIN app_mcp_prompt_translation pt
                ON p.id = pt.app_mcp_prompt_id
                AND pt.language_id = UNHEX(:languageId)
            WHERE a.app_secret IS NOT NULL
            ORDER BY a.name, p.name',
            ['languageId' => Defaults::LANGUAGE_SYSTEM],
        );
    }

    protected function registerCapability(RegistryInterface $registry, array $row): void
    {
        $appName = (string) $row['app_name'];
        $name = (string) $row['name'];
        $promptName = $this->capabilityName($appName, $name);

        if ($this->isReservedName($promptName, $appName, 'prompt')) {
            return;
        }

        $description = $this->resolveDescription($row, $promptName);

        $prompt = new Prompt(
            name: $promptName,
            title: isset($row['label']) && $row['label'] !== '' ? (string) $row['label'] : null,
            description: $description,
        );

        $appSecret = (string) $row['app_secret'];
        $url = (string) $row['url'];

        $registry->registerPrompt($prompt, function (RequestContext $context) use ($promptName, $appSecret, $url): string {
            return $this->executor->execute($promptName, $appSecret, $url, []);
        }, [], true);
    }
}
