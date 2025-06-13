<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

#[Package('framework')]
abstract class CookieStruct extends Struct
{
    public ?string $snippetName;

    public ?string $snippetDescription;

    public ?string $cookie;

    public ?string $value;

    public ?string $expiration;

    public function getApiAlias(): string
    {
        return 'cookie_struct';
    }

    /**
     * @return array<array-key, mixed>
     */
    public function jsonSerialize(): array
    {
        $vars = get_object_vars($this);
        $this->convertDateTimePropertiesToJsonStringRepresentation($vars);

        // Remove null values from the output (for small payloads in store API)
        return array_filter($vars, function ($value) {
            return $value !== null;
        });
    }
}
