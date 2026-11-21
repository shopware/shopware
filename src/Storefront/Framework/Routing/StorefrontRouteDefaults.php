<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
final class StorefrontRouteDefaults
{
    /**
     * Route that handles login when the requested route requires authentication.
     * Defaults to `frontend.account.login.page`.
     */
    public const ATTRIBUTE_LOGIN_ROUTE = '_loginRoute';

    private function __construct()
    {
    }
}
