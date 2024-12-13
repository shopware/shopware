<?php declare(strict_types=1);

namespace Shopware\Core\LoginConfig\Builder;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class LoginConfigItem
{
    /**
     * @param array<string, mixed> $additionalData
     */
    public function __construct(
        public readonly string $key,
        public readonly ?string $snippetKey = null,
        public readonly ?string $icon = null,
        public readonly ?string $class = null,
        public readonly ?string $clientId = null,
        public readonly ?string $clientSecret = null,
        public readonly ?string $redirectUri = null,
        public readonly ?string $baseUrl = null,
        public readonly ?array $additionalData = [],
    ) {}

    /**
     * @return array{key: string, snippet_key: ?string, icon: ?string, class: ?string, client_id: ?string, client_secret: ?string, redirect_uri: ?string, base_url: ?string, additional_data: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'snippet_key' => $this->snippetKey,
            'icon' => $this->icon,
            'class' => $this->class,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'base_url' => $this->baseUrl,
            'additional_data' => $this->additionalData,
        ];
    }

    /**
     * @param array{snippet_key: ?string, icon: ?string, class: ?string, client_id: ?string, client_secret: ?string, redirect_uri: ?string, base_url: ?string, additional_data: array<string, mixed>} $array
     */
    public static function fromArray(string $key, array $array): LoginConfigItem
    {
        return new self(
            $key,
            $array['snippet_key'] ?? null,
            $array['icon'] ?? null,
            $array['class'] ?? null,
            $array['client_id'] ?? null,
            $array['client_secret'] ?? null,
            $array['redirect_uri'] ?? null,
            $array['base_url'] ?? null,
            $array['additional_data'] ?? [],
        );
    }
}
