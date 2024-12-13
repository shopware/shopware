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
        private readonly string $key,
        private readonly ?string $snippetKey = null,
        private readonly ?string $icon = null,
        private readonly ?string $class = null,
        private readonly ?string $clientId = null,
        private readonly ?string $clientSecret = null,
        private readonly ?string $redirectUri = null,
        private readonly ?string $baseUrl = null,
        private readonly ?array $additionalData = [],
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

    public function getKey(): string
    {
        return $this->key;
    }

    public function getSnippetKey(): ?string
    {
        return $this->snippetKey;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function getClientSecret(): ?string
    {
        return $this->clientSecret;
    }

    public function getRedirectUri(): ?string
    {
        return $this->redirectUri;
    }

    public function getBaseUrl(): ?string
    {
        return $this->baseUrl;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAdditionalData(): array
    {
        return $this->additionalData;
    }


}
