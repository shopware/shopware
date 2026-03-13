<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Loader;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Mcp\Capability\Registry\Loader\LoaderInterface;
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
class AppMcpPromptLoader implements LoaderInterface
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
            $prompts = $this->loadActiveAppPrompts();
        } catch (DBALException) {
            return;
        }

        foreach ($prompts as $promptData) {
            $appName = (string) $promptData['app_name'];
            $name = (string) $promptData['name'];
            $promptName = $appName . '-' . $name;

            $description = (string) ($promptData['label'] ?? $promptData['description'] ?? $promptName);

            $prompt = new Prompt(
                name: $promptName,
                description: $description,
            );

            $appSecret = (string) $promptData['app_secret'];
            $url = (string) $promptData['url'];

            $registry->registerPrompt($prompt, function (RequestContext $context) use ($promptName, $appSecret, $url): string {
                return $this->executor->execute($promptName, $appSecret, $url, []);
            }, [], true);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadActiveAppPrompts(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT
                p.name,
                p.url,
                a.name AS app_name,
                a.app_secret,
                COALESCE(pt_locale.label, pt_default.label) AS label,
                COALESCE(pt_locale.description, pt_default.description) AS description
            FROM app_mcp_prompt p
            INNER JOIN app a ON p.app_id = a.id AND a.active = 1
            LEFT JOIN app_mcp_prompt_translation pt_locale
                ON p.id = pt_locale.app_mcp_prompt_id
                AND pt_locale.language_id = (
                    SELECT l.id FROM `language` l
                    INNER JOIN locale lo ON l.locale_id = lo.id AND lo.code = :locale
                    LIMIT 1
                )
            LEFT JOIN app_mcp_prompt_translation pt_default
                ON p.id = pt_default.app_mcp_prompt_id
                AND pt_default.language_id = (
                    SELECT l2.id FROM `language` l2
                    INNER JOIN locale lo2 ON l2.locale_id = lo2.id AND lo2.code = :fallback
                    LIMIT 1
                )
            WHERE a.app_secret IS NOT NULL
            ORDER BY a.name, p.name',
            ['locale' => $locale = $this->resolveSystemLocale(), 'fallback' => $locale],
        );
    }

    private function resolveSystemLocale(): string
    {
        try {
            $code = $this->connection->fetchOne(
                'SELECT lo.code FROM `language` l INNER JOIN locale lo ON l.locale_id = lo.id WHERE l.id = UNHEX(:id) LIMIT 1',
                ['id' => Defaults::LANGUAGE_SYSTEM],
            );

            return \is_string($code) && $code !== '' ? $code : 'en-GB';
        } catch (\Throwable) {
            return 'en-GB';
        }
    }
}
