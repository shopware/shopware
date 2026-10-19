<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Cookie;

use Shopware\Core\Framework\App\Feature\AppFeatureConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @internal
 *
 * @phpstan-type CookiePayload array{snippet_name: string, snippet_description?: string|null, cookie?: string|null, value?: string|null, expiration?: int|null, entries?: list<array<string, mixed>>}
 */
#[Package('framework')]
readonly class CookieConfig implements AppFeatureConfig
{
    /**
     * @param list<array<string, mixed>> $entries
     */
    public function __construct(
        public string $snippetName,
        public ?string $snippetDescription,
        public ?string $cookie,
        public ?string $value,
        public ?int $expiration,
        public array $entries,
    ) {
    }

    public function getName(): string
    {
        return $this->snippetName;
    }

    /**
     * @return CookiePayload
     */
    public function toArray(): array
    {
        return [
            'snippet_name' => $this->snippetName,
            'snippet_description' => $this->snippetDescription,
            'cookie' => $this->cookie,
            'value' => $this->value,
            'expiration' => $this->expiration,
            'entries' => $this->entries,
        ];
    }
}
