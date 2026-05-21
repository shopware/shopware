<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Scope;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\IdentityLinkingCapability;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Canonical list of UCP OAuth scopes (mirrors what we publish in the profile
 * config.scopes object).
 *
 * @internal
 */
#[Package('framework')]
class UcpScopeRegistry
{
    public const ALL_SCOPES = [
        IdentityLinkingCapability::SCOPE_CART_MANAGE,
        IdentityLinkingCapability::SCOPE_ORDER_READ,
        IdentityLinkingCapability::SCOPE_ORDER_MANAGE,
    ];

    /**
     * Scopes that the business considers **optional** at request time, per
     * identity-linking.md §"Identity Optional". An operation tagged identity-
     * optional accepts both anonymous and bearer-authenticated calls; the
     * difference is observability (which user placed the order) rather than
     * authorization.
     *
     * In Shopware: `cart:manage` is identity-optional (guest carts allowed);
     * `order:*` is identity-required.
     */
    public const IDENTITY_OPTIONAL_SCOPES = [
        IdentityLinkingCapability::SCOPE_CART_MANAGE,
    ];

    public function has(string $scope): bool
    {
        return \in_array($scope, self::ALL_SCOPES, true);
    }

    /**
     * @return list<string>
     */
    public function all(): array
    {
        return self::ALL_SCOPES;
    }

    /**
     * @return list<string>
     */
    public function identityOptionalScopes(): array
    {
        return self::IDENTITY_OPTIONAL_SCOPES;
    }

    public function isIdentityOptional(string $scope): bool
    {
        return \in_array($scope, self::IDENTITY_OPTIONAL_SCOPES, true);
    }

    public function describe(string $scope): string
    {
        return match ($scope) {
            IdentityLinkingCapability::SCOPE_CART_MANAGE => 'Read, create and modify your cart on this shop',
            IdentityLinkingCapability::SCOPE_ORDER_READ => 'See your order history',
            IdentityLinkingCapability::SCOPE_ORDER_MANAGE => 'Place orders on your behalf and manage existing orders (returns, cancellations)',
            default => $scope,
        };
    }
}
