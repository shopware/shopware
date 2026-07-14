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
 * @implements AppFeatureDefinition<CookieConfig>
 *
 * @phpstan-type CookiePayload array{snippet_name: string, snippet_description?: string|null, cookie?: string|null, value?: string|null, expiration?: int|null, entries?: list<array<string, mixed>>}
 */
#[Package('framework')]
class CookieFeatureDefinition implements AppFeatureDefinition
{
    public function getType(): string
    {
        return 'cookie';
    }

    public function getConfigClass(): string
    {
        return CookieConfig::class;
    }

    public function fromApp(Manifest $manifest, Filesystem $appFilesystem, string $defaultLocale): array
    {
        return array_map(
            static function (array $group): CookieConfig {
                /** @var list<array<string, mixed>> $entries */
                $entries = $group['entries'] ?? [];

                return new CookieConfig(
                    (string) $group['snippet_name'],
                    isset($group['snippet_description']) ? (string) $group['snippet_description'] : null,
                    isset($group['cookie']) ? (string) $group['cookie'] : null,
                    isset($group['value']) ? (string) $group['value'] : null,
                    isset($group['expiration']) ? (int) $group['expiration'] : null,
                    $entries,
                );
            },
            $manifest->getCookies()?->getCookies() ?? [],
        );
    }

    /**
     * @return CookiePayload
     */
    public function toPayload(AppFeatureConfig $declared, ?AppFeatureConfig $stored): array
    {
        return [
            'snippet_name' => $declared->snippetName,
            'snippet_description' => $declared->snippetDescription,
            'cookie' => $declared->cookie,
            'value' => $declared->value,
            'expiration' => $declared->expiration,
            'entries' => $declared->entries,
        ];
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
}
