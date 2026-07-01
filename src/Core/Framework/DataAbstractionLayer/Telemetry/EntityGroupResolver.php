<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Telemetry;

use Shopware\Core\Framework\Log\Package;

/**
 * Buckets a DAL entity name into a small, bounded set of groups, so the large, plugin-extensible set of
 * entity names does not blow up the metric label cardinality.
 *
 * Classification is O(1) in two steps: an exact full-name lookup for the entities that don't follow the convention,
 * then a fallback where first underscore-delimited token determines the group (`product_price`, `product_media` → `product`).
 *
 * Owns its bounded output set (closed map, `other` as default), so the consuming metric labels may use
 * `policy: open`. Known outputs: product, category, customer, order, media, content, cms, rule, system,
 * b2b, other.
 *
 * Shared resolver — reused by the HTTP request (admin-CRUD `domain`) and DAL search collectors.
 *
 * The hardcoded maps are intentional (optimized for deletion): while the label set is still changing,
 * one map with no extension API is simpler to maintain. Once the groups are stable we can switch to a cleaner approach,
 * e.g. a telemetry-group attribute on the EntityDefinition.
 *
 * @internal
 *
 * @final
 *
 * @experimental feature:TELEMETRY_METRICS stableVersion:v6.8.0
 */
#[Package('framework')]
class EntityGroupResolver
{
    /**
     * Exact full entity names mapping for when root-token mapping is misleading or fragile (e.g. `main` as a key).
     *
     * @var array<string, string>
     */
    private const ENTITIES = [
        'main_category' => 'category',

        // property_group* are product catalog attributes, but `property*` is too generic to use for mapping
        'property_group' => 'product',
        'property_group_option' => 'product',
        'property_group_translation' => 'product',
        'property_group_option_translation' => 'product',

        // `custom_field*` are platform config, but custom entities are app/plugin-defined
        // (`custom_entity_*` / `ce_*`) third-party domain data — those must fall through to `other`,
        'custom_entity' => 'system',
        'custom_field' => 'system',
        'custom_field_set' => 'system',
        'custom_field_set_relation' => 'system',
    ];

    /**
     * Root entity token → group. The root token is the part before the first underscore (or the whole name
     * when it has none), so e.g. `mail` covers `mail_template`/`mail_header_footer`. Unlisted roots fall through to `other`.
     *
     * Third-party entities use a vendor prefix (and custom entities the sanctioned `custom_entity_*`/`ce_*`,
     * handled in ENTITIES), so they don't collide with these first-party roots. Generic-looking roots
     * (`state`, `user`, `log`, …) would only catch a convention-violating plugin entity — a rare, low-impact
     * mislabel — so they stay as tokens rather than being exhaustively exact-mapped.
     *
     * @var array<string, string>
     */
    private const ROOTS = [
        // basic domains
        'product' => 'product',
        'category' => 'category',
        'customer' => 'customer',
        'newsletter' => 'customer',
        'order' => 'order',
        'media' => 'media',
        'cms' => 'cms',
        'rule' => 'rule',
        'b2b' => 'b2b',

        // content
        'landing' => 'content',
        'mail' => 'content',

        // platform configuration & infrastructure
        'acl' => 'system',
        'app' => 'system',
        'country' => 'system',
        'currency' => 'system',
        'language' => 'system',
        'locale' => 'system',
        'salutation' => 'system',
        'tax' => 'system',
        'unit' => 'system',
        'delivery' => 'system',       // delivery_time*
        'payment' => 'system',        // payment_method*
        'shipping' => 'system',       // shipping_method*
        'sales' => 'system',          // sales_channel*
        'plugin' => 'system',
        'integration' => 'system',
        'system' => 'system',         // system_config
        'user' => 'system',
        'scheduled' => 'system',      // scheduled_task
        'number' => 'system',         // number_range*
        'state' => 'system',          // state_machine*
        'seo' => 'system',            // seo_url*
        'import' => 'system',         // import_export*
        'flow' => 'system',
        'webhook' => 'system',
        'snippet' => 'system',
        'theme' => 'system',
        'log' => 'system',            // log_entry
        'notification' => 'system',
        'tag' => 'system',
    ];

    public function resolve(string $entityName): string
    {
        return self::ENTITIES[$entityName]
            ?? self::ROOTS[strstr($entityName, '_', true) ?: $entityName]
            ?? 'other';
    }
}
