<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('framework')]
class CookieStruct extends Struct
{
    public ?string $snippetName;

    public ?string $snippetDescription;

    public ?string $cookie;

    public ?string $value;

    public ?string $expiration;

    public function getSnippetName(): ?string
    {
        return $this->snippetName;
    }

    public function setSnippetName(?string $snippetName): void
    {
        $this->snippetName = $snippetName;
    }

    public function isSnippetNameUninitializedOrNull(): bool
    {
        return !isset($this->snippetName);
    }

    public function getSnippetDescription(): ?string
    {
        return $this->snippetDescription;
    }

    public function setSnippetDescription(?string $snippetDescription): void
    {
        $this->snippetDescription = $snippetDescription;
    }

    public function isSnippetDescriptionUninitializedOrNull(): bool
    {
        return !isset($this->snippetDescription);
    }

    public function getCookie(): ?string
    {
        return $this->cookie;
    }

    public function setCookie(?string $cookie): void
    {
        $this->cookie = $cookie;
    }

    public function isCookieUninitializedOrNull(): bool
    {
        return !isset($this->cookie);
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
    }

    public function isValueUninitializedOrNull(): bool
    {
        return !isset($this->value);
    }

    public function getExpiration(): ?string
    {
        return $this->expiration;
    }

    public function setExpiration(?string $expiration): void
    {
        $this->expiration = $expiration;
    }

    public function isExpirationUninitializedOrNull(): bool
    {
        return !isset($this->expiration);
    }

    public function getApiAlias(): string
    {
        return 'cookie_struct';
    }
}
