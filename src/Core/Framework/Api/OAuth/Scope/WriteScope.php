<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth\Scope;

use League\OAuth2\Server\Entities\ScopeEntityInterface;

class WriteScope implements ScopeEntityInterface
{
    public const IDENTIFIER = 'write';

    /**
     * Get the scope's identifier.
     */
    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @see http://php.net/manual/en/jsonserializable.jsonserialize.php
     *
     * @return mixed data which can be serialized by <b>json_encode</b>,               which is a value of any type other than a resource
     *
     * @since 5.4.0
     * @deprecated tag:v6.5.0 - return type will be changed to string
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()/* :mixed */
    {
        return self::IDENTIFIER;
    }
}
