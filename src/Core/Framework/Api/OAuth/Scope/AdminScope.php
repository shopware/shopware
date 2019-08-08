<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth\Scope;

use League\OAuth2\Server\Entities\ScopeEntityInterface;

class AdminScope implements ScopeEntityInterface
{
    public const IDENTIFIER = 'admin';

    public function getIdentifier()
    {
        return self::IDENTIFIER;
    }

    public function jsonSerialize()
    {
        return self::IDENTIFIER;
    }
}
