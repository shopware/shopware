<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Rest;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\AbstractRouteScope;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * New Symfony route scope `ucp`, sibling to `store-api`, `api` and `storefront`.
 * Routes carrying this scope go through the UCP authentication/negotiation
 * pipeline (agent header resolution, signature verification, capability gating).
 */
#[Package('framework')]
class UcpRouteScope extends AbstractRouteScope
{
    public const ID = 'ucp';

    /**
     * We intentionally keep `allowedPaths` empty so that `/ucp/*` paths are
     * NOT registered as API prefixes. The storefront RequestTransformer would
     * otherwise skip Sales-Channel domain resolution for our routes, which
     * we need for OAuth Authorize/Token endpoints that rely on the active
     * customer session.
     *
     * Routes still opt into this scope via `defaults: ['_routeScope' => ['ucp']]`
     * on the controller attribute — same as other scopes.
     */
    protected array $allowedPaths = [];

    public function isAllowed(Request $request): bool
    {
        return true;
    }

    public function getId(): string
    {
        return self::ID;
    }
}
