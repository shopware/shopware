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

    public function isSnippetNameUninitializedOrNull(): bool
    {
        return !isset($this->snippetName);
    }

    public function isSnippetDescriptionUninitializedOrNull(): bool
    {
        return !isset($this->snippetDescription);
    }

    public function isCookieUninitializedOrNull(): bool
    {
        return !isset($this->cookie);
    }

    public function isValueUninitializedOrNull(): bool
    {
        return !isset($this->value);
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
