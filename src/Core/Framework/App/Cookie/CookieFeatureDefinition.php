<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Cookie;

use Shopware\Core\Framework\App\Feature\AppFeatureConfig;
use Shopware\Core\Framework\App\Feature\AppFeatureDefinition;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 *
 * @extends AppFeatureDefinition<CookieConfig>
 *
 * @phpstan-import-type CookiePayload from CookieConfig
 */
#[Package('framework')]
class CookieFeatureDefinition extends AppFeatureDefinition
{
    public const TYPE = 'cookie';

    public function getType(): string
    {
        return self::TYPE;
    }

    public function getConfigClass(): string
    {
        return CookieConfig::class;
    }

    public function fromApp(Manifest $manifest, Filesystem $appFilesystem, string $defaultLocale): array
    {
        $configs = [];
        foreach ($manifest->getCookies()?->getCookies() ?? [] as $group) {
            /** @var list<array<string, mixed>> $entries */
            $entries = $group['entries'] ?? [];

            $config = new CookieConfig(
                (string) $group['snippet_name'],
                isset($group['snippet_description']) ? (string) $group['snippet_description'] : null,
                isset($group['cookie']) ? (string) $group['cookie'] : null,
                isset($group['value']) ? (string) $group['value'] : null,
                isset($group['expiration']) ? (int) $group['expiration'] : null,
                $entries,
            );

            $existing = $configs[$config->snippetName] ?? null;
            $configs[$config->snippetName] = $existing === null ? $config : self::merge($existing, $config);
        }

        return array_values($configs);
    }

    /**
     * @return CookiePayload
     */
    public function toPayload(AppFeatureConfig $declared, ?AppFeatureConfig $stored): array
    {
        return $declared->toArray();
    }

    /**
     * @param CookiePayload $payload
     */
    public function fromPayload(array $payload): CookieConfig
    {
        return new CookieConfig(
            $payload['snippet_name'],
            $payload['snippet_description'] ?? null,
            $payload['cookie'] ?? null,
            $payload['value'] ?? null,
            $payload['expiration'] ?? null,
            $payload['entries'] ?? [],
        );
    }

    private static function merge(CookieConfig $first, CookieConfig $second): CookieConfig
    {
        return new CookieConfig(
            $first->snippetName,
            $second->snippetDescription ?? $first->snippetDescription,
            $second->cookie ?? $first->cookie,
            $second->value ?? $first->value,
            $second->expiration ?? $first->expiration,
            [...$first->entries, ...$second->entries],
        );
    }
}
