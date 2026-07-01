<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Telemetry;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves the `operation` label (read / write / delete) for admin-api CRUD requests, `none` for
 * everything else.
 *
 * Derived from the route-name suffix, not the HTTP method (POST covers both `create` and `search`).
 * `api.action.*`, store-api and storefront routes are not CRUD and resolve operation to `none`.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class OperationResolver
{
    private const NONE = 'none';
    private const WRITE = 'write';
    private const READ = 'read';
    private const DELETE = 'delete';

    public function resolve(Request $request): string
    {
        $route = (string) $request->attributes->get('_route', '');

        // version/clone are admin writes/deletes without a CRUD suffix
        $special = match ($route) {
            'api.clone', 'api.createVersion', 'api.mergeVersion' => self::WRITE,
            'api.deleteVersion' => self::DELETE,
            default => null,
        };

        if ($special !== null) {
            return $special;
        }

        // allow only admin CRUD routes (`api.{entity}.{action}`), exclude the action API
        if (!str_starts_with($route, 'api.') || str_starts_with($route, 'api.action.')) {
            return 'none';
        }

        $suffix = substr($route, (int) strrpos($route, '.') + 1);

        return match ($suffix) {
            'list', 'detail', 'search', 'search-ids', 'aggregate' => self::READ,
            'create', 'update' => self::WRITE,
            'delete' => self::DELETE,
            default => self::NONE,
        };
    }
}
