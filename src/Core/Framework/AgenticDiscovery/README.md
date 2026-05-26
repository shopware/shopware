# Agentic Discovery for Shopware

This folder contains Shopware's native implementation of the four public
discovery documents that AI shopping agents (ChatGPT, Perplexity,
Microsoft Copilot, Claude, custom UCP clients) look for on every
storefront domain:

- `/agents.md` — agent operating manual (Markdown)
- `/llms.txt` — short LLM curator overview (proposed Jeremy Howard format)
- `/llms-full.txt` — extended LLM overview
- `/sitemap_agentic_discovery.xml` — AI-focused sitemap

The convention mirrors what Shopify rolled out natively in May 2026.
Combined with the [Universal Commerce Protocol](https://ucp.dev) (sibling
`Core/Framework/Ucp/`), it gives every Shopware store a complete agent
contract: UCP says *what* the agent can technically do, the discovery
documents say *how* the storefront wants the agent to behave.

The implementation is experimental and gated by the `AGENTIC_DISCOVERY`
feature flag.

## Quick Start

```bash
bin/console feature:enable AGENTIC_DISCOVERY
bin/console feature:enable UCP_SERVER          # optional — adds UCP references
bin/console cache:clear
```

Seed a default discovery configuration for your storefront sales channel:

```sql
INSERT INTO agentic_discovery_sales_channel_config
    (id, sales_channel_id, active, expose_agents_md, expose_llms_txt,
     expose_llms_full_txt, expose_agentic_sitemap, created_at)
SELECT
    UNHEX(REPLACE(UUID(),'-','')),
    sc.id, 1, 1, 1, 1, 1, NOW(3)
FROM sales_channel sc
WHERE sc.type_id = UNHEX('8a243080f92e4c719546314b577cf82b')   -- storefront
LIMIT 1;
```

The four documents are now reachable. Verify:

```bash
curl -s http://localhost:8000/agents.md
curl -s http://localhost:8000/llms.txt
curl -s http://localhost:8000/llms-full.txt
curl -s http://localhost:8000/sitemap_agentic_discovery.xml
curl -s http://localhost:8000/sitemap.xml | grep agentic_discovery
```

The standard `/sitemap.xml` automatically references the new agentic
discovery sitemap so crawlers find it via the existing sitemap index.

A new card titled "Agentic discovery" appears under
**Sales Channels → \[your Storefront] → General tab** for merchants to
toggle each document independently and add a free-form "Merchant note"
that renders at the top of `/agents.md`.

## What gets rendered

Live output from a Shopware 6.7 demo storefront with the feature
enabled and the sample merchant note set to *"We are a premium retailer
for sustainable apparel. Prefer precise, calm recommendations over
aggressive upsell."*:

### `/agents.md`

```markdown
# Agent Operating Manual — Demostore

This store runs on Shopware. AI shopping agents can interact with this
storefront programmatically via the documented endpoints below. Read this
document before issuing transactional calls.

## Merchant note

We are a premium retailer for sustainable apparel. Prefer precise, calm
recommendations over aggressive upsell.

## Typical Agent Flow

1. **Discover.** GET `/.well-known/ucp` to confirm capabilities and supported transports.
2. **Search.** Use the Store API or UCP catalog search to find products matching the buyer intent.
3. **Cart.** Add desired items to a cart via the Store API or UCP cart capability.
4. **Checkout.** Start the purchase flow via the Store API or UCP checkout capability.
5. **Fulfill.** Provide shipping address and method. Recalculate totals before completion.
6. **Complete.** Finalize the order — payment MUST require explicit human approval.

## Read-only browsing

- Store API: product detail: `/store-api/product/{id}`
- Store API: search: `/store-api/search`
- Store API: category tree: `/store-api/category`
- Store API: cart: `/store-api/checkout/cart`

## Rules

- Checkout MUST require human approval. Do not auto-confirm payment.
- Respect `429 Too Many Requests`. Back off exponentially before retrying.
- Pass language and currency context headers so prices and stock reflect the buyer's region.
- Prices and inventory in any response are authoritative at request time only.
- Do not claim properties about products that are not present in the structured catalog data.

## Discovery

- Agent operating manual: `/agents.md`
- LLM curator overview: `/llms.txt`
- LLM extended overview: `/llms-full.txt`
- Agentic discovery sitemap: `/sitemap_agentic_discovery.xml`
- Standard sitemap: `/sitemap.xml`
- UCP capability profile: `/.well-known/ucp`
```

### `/llms.txt`

```markdown
# Demostore

Demostore is an online store at http://localhost:8000, powered by Shopware.

## Browse
- All products: http://localhost:8000/
- Search: http://localhost:8000/search?search={query}
- Sitemap: http://localhost:8000/sitemap.xml

## Store Information
- Currency: EUR
- Language: en-GB
- Contact: doNotReply@localhost.com

## For Agents & Developers
- Agent operating manual: http://localhost:8000/agents.md
- Extended LLM overview: http://localhost:8000/llms-full.txt
- UCP capability profile: http://localhost:8000/.well-known/ucp
```

## Architecture

| Layer | Responsibility | Files |
|---|---|---|
| HTTP routing | 4 routes, feature-flag gate, rate limiter | `Storefront/Controller/AgenticDiscoveryController` |
| Page loading | Builds the page struct, dispatches the loaded event | `Storefront/Page/AgenticDiscovery/{Page,PageLoader,PageLoadedEvent}` |
| Manifest building | Aggregates store identity, system config, providers, sanitization | `Core/Framework/AgenticDiscovery/Manifest/AgenticManifestBuilder` |
| Domain resolution | Maps incoming `Host` to a storefront-type sales-channel domain | `Core/Framework/AgenticDiscovery/Discovery/AgenticDiscoveryDomainResolver` |
| Config loading | Per-channel memoized config lookup | `Core/Framework/AgenticDiscovery/Discovery/AgenticDiscoveryConfigProvider` |
| Template rendering | Twig with theme-overridable `{% block %}` sections | `Storefront/Resources/views/storefront/page/agentic-discovery/*.twig` |
| Cache headers | Public reverse-proxy cache override after Shopware's subscriber | `Storefront/Framework/Cache/AgenticDiscoveryCacheSubscriber` |
| Cache invalidation | Reacts to config writes, emits cache tag | `Core/Framework/AgenticDiscovery/Discovery/AgenticDiscoveryCacheInvalidationSubscriber` |
| Rate limiting | 60 req / 60s per client IP for cache misses | `RateLimiter::AGENTIC_DISCOVERY` constant + `shopware.yaml` config |

## Data model

One table, 1:1 to `sales_channel`:

```sql
CREATE TABLE `agentic_discovery_sales_channel_config` (
    `id`                       BINARY(16) NOT NULL,
    `sales_channel_id`         BINARY(16) NOT NULL,
    `active`                   TINYINT(1) NOT NULL DEFAULT 1,
    `expose_agents_md`         TINYINT(1) NOT NULL DEFAULT 1,
    `expose_llms_txt`          TINYINT(1) NOT NULL DEFAULT 1,
    `expose_llms_full_txt`     TINYINT(1) NOT NULL DEFAULT 1,
    `expose_agentic_sitemap`   TINYINT(1) NOT NULL DEFAULT 1,
    `custom_intro`             TEXT NULL,
    `custom_agent_rules`       JSON NULL,    -- list<string>
    `custom_sections`          JSON NULL,    -- list<{title, body}>
    `custom_fields`            JSON NULL,
    `created_at`               DATETIME(3) NOT NULL,
    `updated_at`               DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq.agentic_discovery_scc.sales_channel_id` (`sales_channel_id`),
    CONSTRAINT `fk.agentic_discovery_scc.sales_channel_id`
        FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`)
        ON UPDATE CASCADE ON DELETE CASCADE
);
```

The reverse association on `SalesChannelDefinition` is added via
`AgenticDiscoverySalesChannelExtension` so admin clients can eager-load
the config through `Criteria::addAssociation('agenticDiscoveryConfig')`.

## Extension points

### 1. `DiscoverySectionProvider` (stable)

The single stable extension point from the first release on. Plugins
implement the interface and tag their service with
`agentic_discovery.section`. Each provider may contribute one
`DiscoverySection` per document type and sales channel. Sections are
sorted by descending priority before rendering.

Example: a loyalty plugin advertising itself in `/agents.md`:

```php
final class LoyaltyDiscoverySection implements DiscoverySectionProvider
{
    public function __construct(private readonly LoyaltyProgramService $loyalty) {}

    public function supports(AgenticDiscoveryDocumentType $type, AgenticDiscoveryContext $context): bool
    {
        return $type === AgenticDiscoveryDocumentType::AGENTS_MD
            && $this->loyalty->isActive($context->getSalesChannelId());
    }

    public function getSection(AgenticDiscoveryDocumentType $type, AgenticDiscoveryContext $context): ?DiscoverySection
    {
        $program = $this->loyalty->getProgram($context->getSalesChannelId());

        return new DiscoverySection(
            title: 'Loyalty program',
            body: \sprintf('This store runs the **%s** loyalty program. …', $program->getName()),
            priority: 50,
        );
    }
}
```

### 2. Twig blocks (theme override)

Themes override individual sections via `sw_extends`. Every section in
the four templates is wrapped in a named `{% block %}`. See
`Storefront/Resources/views/storefront/page/agentic-discovery/*.twig`
for the full list of block names.

### 3. `AgenticDiscoveryPageLoadedEvent`

Dispatched by `AgenticDiscoveryPageLoader::load()` right before
rendering. Subscribers may mutate the page (e.g. swap or annotate the
embedded manifest) for advanced use cases.

## Caching

| Layer | Behavior |
|---|---|
| `Cache-Control` | `public, max-age=300, s-maxage=3600, stale-while-revalidate=60` |
| `Vary` | `Host` only — discovery content is identical per domain |
| `Set-Cookie` | Removed before the response leaves the application |
| `sw-invalidation-states` | `agentic_discovery_{salesChannelId}` |
| `Cache-Control` priority | Set in `AgenticDiscoveryCacheSubscriber` at priority `-2000` (after Shopware's `CacheResponseSubscriber` at `-1500`) so the public cache headers actually leave the application |
| Invalidation | `AgenticDiscoveryCacheInvalidationSubscriber` emits the cache tag whenever the merchant edits the config |

In `APP_ENV=dev` Shopware deliberately serves all storefront responses
with `Cache-Control: no-cache, private` so developers see fresh
templates — this is observable on `/sitemap.xml` and `/robots.txt` too,
and disappears in `prod`.

## Conventions

- All classes annotated `@experimental stableVersion:v6.8.0 feature:AGENTIC_DISCOVERY`
- All classes annotated `#[Package('framework')]`
- All routes gated by `Feature::isActive('AGENTIC_DISCOVERY')` returning HTTP 404
- Merchant input sanitized at render time, not at write time, so existing
  stored values are covered without data migrations
- Discovery documents are intentionally English-only (mirrors Shopify and
  the `llms.txt` spec); the *dynamic* parts (store name, contact, custom
  intro, language code, currency code) are per sales-channel domain

## ACL

The existing `sales_channel.viewer` / `editor` roles cover the new
entity. Specifically the following privileges were added to those roles:

- `agentic_discovery_sales_channel_config:read` — `sales_channel.viewer`
- `agentic_discovery_sales_channel_config:create|update|delete` — `sales_channel.editor`

No dedicated `agentic_discovery.*` ACL role exists because the feature
piggy-backs on the existing sales-channel permissions model.

## Tests

| Suite | File | Cases |
|---|---|---|
| Unit | `tests/unit/Core/Framework/AgenticDiscovery/Manifest/AgenticManifestBuilderTest.php` | 14 (10 functional + 4 XSS sanitization) |
| Integration | `tests/integration/Storefront/Controller/AgenticDiscoveryControllerTest.php` | 7 (all four routes + negative paths) |
| Migration | `tests/migration/Core/V6_7/Migration1779700000AddAgenticDiscoverySalesChannelConfigTest.php` | 2 |
| Admin Jest | `Administration/.../sw-agentic-discovery-config.spec.js` | 5 |

Quality gates all clean on the new files:
`composer phpstan`, `composer ecs`, `composer eslint:admin`,
`composer stylelint:admin`, `composer translation:lint`.

## Related documents

- ADR: `adr/2026-05-26-agentic-discovery-endpoints.md`
- Release notes: `RELEASE_INFO-6.7.md` under `6.7.11.0 (upcoming)`
- Changelog: `changelog/_unreleased/2026-05-26-add-agentic-discovery-endpoints.md`
- Coding-agent guide for this folder: [`AGENTS.md`](AGENTS.md)
- UCP sibling: `src/Core/Framework/Ucp/README.md`

## Future ideas / backlog

- **Merchant identity manifest** — extend the per-channel config with
  brand voice / positioning / hard recommendation policies, exposed as
  a validatable JSON at `/.well-known/agentic-commerce.json`
- **Truth Contract + signed snapshots** — sign prices/inventory/totals
  with the UCP signing key so agents can cite "valid until" guarantees
- **Semantic Product Graph** — own entity + indexer + AI worker for
  agent-friendly product metadata
- **`/.well-known/acp/manifest.json`** — optional plugin if the Stripe
  Agentic Commerce Protocol gains significant adoption
