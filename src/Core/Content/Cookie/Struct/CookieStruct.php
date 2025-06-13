<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @implements \ArrayAccess<string, mixed>
 */
#[Package('framework')]
abstract class CookieStruct extends Struct implements \ArrayAccess
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

    /**
     * @param mixed $offset
     */
    public function offsetExists($offset): bool
    {
        return property_exists($this, $offset);
    }

    /**
     * @param mixed $offset
     */
    public function offsetGet($offset): mixed
    {
        return match ($offset) {
            'snippetName' => $this->snippetName,
            'snippetDescription' => $this->snippetDescription,
            'cookie' => $this->cookie,
            'value' => $this->value,
            'expiration' => $this->expiration,
            default => null,
        };
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        switch ($offset) {
            case 'snippetName':
                $this->snippetName = $value;
                break;
            case 'snippetDescription':
                $this->snippetDescription = $value;
                break;
            case 'cookie':
                $this->cookie = $value;
                break;
            case 'value':
                $this->value = $value;
                break;
            case 'expiration':
                $this->expiration = $value;
                break;
        }
    }

    /**
     * @param mixed $offset
     */
    public function offsetUnset($offset): void
    {
        switch ($offset) {
            case 'snippetName':
                $this->snippetName = null;
                break;
            case 'snippetDescription':
                $this->snippetDescription = null;
                break;
            case 'cookie':
                $this->cookie = null;
                break;
            case 'value':
                $this->value = null;
                break;
            case 'expiration':
                $this->expiration = null;
                break;
        }
    }
}
