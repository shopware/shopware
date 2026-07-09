<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Telemetry;

use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves the polymorphic `domain` label of a main HTTP request (the `area` label disambiguates the
 * overlapping values, so a single `other` default is enough).
 *
 * By route shape:
 *  - frontend.* / store-api.*     → functional group (ordered prefix match, first hit wins)
 *  - api.action.*                 → action domain (segment after `api.action.`); `api.action.sync` excluded
 *  - admin CRUD / clone / version → uses entity name mapping from EntityGroupResolver (entity from the `entityName`/`entity` attribute)
 *  - anything else                → other
 *
 * Bounded output set (closed maps, `other` as default), so the consuming metric labels may use
 * `policy: open`. Route → group results are memoized per process (the storefront/store api/admin action route keyspace
 * is finite and fixed, resulting map is small, so reset/eviction is not implemented).
 *
 * The hardcoded maps are intentional (optimized for deletion): while the label set is still changing,
 * one map with no extension API is simpler to maintain. Once the groups are stable we can switch to a cleaner approach,
 * e.g. a telemetry-group attribute on the route.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class DomainResolver
{
    /**
     * Ordered route-name prefix → functional group, first match wins. Grouped by target domain and
     * roughly by traffic (hot routes first, so the common case exits early); within that, more specific
     * prefixes precede their broader fallback (auth/order before customer, cart before checkout).
     *
     * @var array<string, string>
     */
    private const FUNCTIONAL_GROUPS = [
        // product
        'frontend.detail' => 'product',
        'frontend.product' => 'product',
        'store-api.product' => 'product',

        // category / navigation
        'frontend.navigation' => 'category',
        'store-api.navigation' => 'category',
        'store-api.category' => 'category',

        // content (home + cms + landing pages)
        'frontend.home' => 'content',
        'frontend.cms' => 'content',
        'frontend.landing' => 'content',
        'store-api.cms' => 'content',
        'store-api.landing-page' => 'content',

        // search
        'frontend.search' => 'search',
        'store-api.search' => 'search',

        // cart (before checkout)
        'frontend.cart' => 'cart',
        'frontend.checkout.cart' => 'cart',
        'frontend.checkout.line-item' => 'cart',
        'store-api.checkout.cart' => 'cart',

        // checkout
        'frontend.checkout' => 'checkout',
        'store-api.checkout' => 'checkout',

        // auth (before customer)
        'frontend.account.login' => 'auth',
        'frontend.account.logout' => 'auth',
        'frontend.account.register' => 'auth',
        'frontend.account.recover' => 'auth',
        'store-api.account.login' => 'auth',
        'store-api.account.logout' => 'auth',
        'store-api.account.register' => 'auth',
        'store-api.account.recover' => 'auth',

        // order (before customer)
        'frontend.account.order' => 'order',
        'frontend.account.edit-order' => 'order',
        'store-api.order' => 'order',

        // customer (account catch-all + wishlist)
        'frontend.account' => 'customer',
        'frontend.wishlist' => 'customer',
        'store-api.account' => 'customer',

        // media
        'frontend.media' => 'media',
        'store-api.media' => 'media',
    ];

    /**
     * Segment after `api.action.` → action domain (`system-config`→core; `sync` absent, has its own area).
     *
     * @var array<string, string>
     */
    private const ACTION_DOMAINS = [
        'cache' => 'cache',
        'index' => 'indexing',
        'indexing' => 'indexing',
        'import-export' => 'import-export',
        'document' => 'document',
        'media' => 'media',
        'order' => 'order',
        'promotion' => 'promotion',
        'system-config' => 'core',
        'user' => 'user',
        'scheduled-task' => 'scheduled-task',
        'number-range' => 'number-range',
        'store' => 'store',
    ];

    private const VERSION_SPECIALS = ['api.clone', 'api.createVersion', 'api.mergeVersion', 'api.deleteVersion'];

    /**
     * @var array<string, string>
     */
    private array $cache = [];

    public function __construct(private readonly EntityGroupResolver $entityGroupResolver)
    {
    }

    public function resolve(Request $request): string
    {
        $route = (string) $request->attributes->get('_route', '');

        // route-only branches are pure → memoized (finite route keyspace, see class docblock)
        if (str_starts_with($route, 'frontend.') || str_starts_with($route, 'store-api.')) {
            return $this->cache[$route] ??= $this->functionalGroup($route);
        }

        if ($route !== 'api.action.sync' && str_starts_with($route, 'api.action.')) {
            return $this->cache[$route] ??= $this->actionDomain($route);
        }

        // admin CRUD: the entity is the `entityName` route default
        $entityName = $request->attributes->get('entityName');
        if (\is_string($entityName) && $entityName !== '') {
            return $this->entityGroup($entityName);
        }

        // clone / version: the entity is the `entity` route param
        if (\in_array($route, self::VERSION_SPECIALS, true)) {
            $entity = $request->attributes->get('entity');
            if (\is_string($entity) && $entity !== '') {
                return $this->entityGroup($entity);
            }
        }

        return 'other';
    }

    /**
     * Maps an admin API resource name to its entity group. Admin routes carry the hyphenated resource
     * name (e.g. `product-manufacturer`); entity groups key on the snake_case entity name.
     */
    private function entityGroup(string $resourceName): string
    {
        return $this->entityGroupResolver->resolve(str_replace('-', '_', $resourceName));
    }

    private function functionalGroup(string $route): string
    {
        foreach (self::FUNCTIONAL_GROUPS as $prefix => $group) {
            if (str_starts_with($route, $prefix)) {
                return $group;
            }
        }

        return 'other';
    }

    private function actionDomain(string $route): string
    {
        // strip 'api.action.' (11 chars), take the next dot-delimited segment
        $segment = explode('.', substr($route, 11))[0];

        return self::ACTION_DOMAINS[$segment] ?? 'other';
    }
}
