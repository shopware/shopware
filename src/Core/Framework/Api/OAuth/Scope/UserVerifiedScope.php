<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth\Scope;

use League\OAuth2\Server\Entities\ScopeEntityInterface;

class UserVerifiedScope implements ScopeEntityInterface
{
    public const IDENTIFIER = 'user-verified';

    public function getIdentifier()
    {
        return self::IDENTIFIER;
    }

    /**
     * @deprecated tag:v6.5.0 - return type will be changed to string
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()/* :mixed */
    {
        return self::IDENTIFIER;
    }
}
