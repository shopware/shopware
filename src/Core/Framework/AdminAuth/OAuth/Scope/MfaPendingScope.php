<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\OAuth\Scope;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use Shopware\Core\Framework\AdminAuth\PendingTokenGuard;
use Shopware\Core\Framework\Log\Package;

/**
 * Scope carried by a "MFA pending" access token issued between the first and second factor.
 *
 * A token holding only this scope is powerless against the regular admin API: every real route
 * requires write/admin/read scopes via ACL, none of which a pending token has. The
 * {@see PendingTokenGuard} additionally rejects such tokens on
 * every authenticated route. Marker scopes (`admin-mfa-challenge:<id>` / `admin-mfa-methods:<csv>`)
 * reuse this class with a custom identifier.
 *
 * @internal
 */
#[Package('framework')]
class MfaPendingScope implements ScopeEntityInterface
{
    final public const IDENTIFIER = 'admin-mfa-pending';

    /**
     * @param non-empty-string $identifier
     */
    public function __construct(private readonly string $identifier = self::IDENTIFIER)
    {
    }

    /**
     * @return non-empty-string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function jsonSerialize(): mixed
    {
        return $this->identifier;
    }
}
