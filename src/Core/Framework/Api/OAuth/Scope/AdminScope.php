<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth\Scope;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesFinal;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
#[BecomesFinal(version: 'v6.8.0')]
class AdminScope implements ScopeEntityInterface
{
    final public const IDENTIFIER = 'admin';

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function jsonSerialize(): mixed
    {
        return self::IDENTIFIER;
    }
}
