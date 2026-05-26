# Shopware Agentic Discovery

## Overview

This module implements **native delivery of the four agentic discovery
documents** Shopware storefronts expose to AI shopping agents
(ChatGPT, Perplexity, Microsoft Copilot, Claude, custom UCP clients):

- `/agents.md` — agent operating manual (Markdown)
- `/llms.txt` — short LLM curator overview (proposed Jeremy Howard format)
- `/llms-full.txt` — extended LLM overview
- `/sitemap_agentic_discovery.xml` — AI-focused sitemap

The convention mirrors what Shopify rolled out natively in May 2026.
UCP (sibling `Core/Framework/Ucp/`) answers *what an agent can technically
do*; this module answers *how the storefront wants the agent to behave*.

## Status

**Experimental** — gated behind the `AGENTIC_DISCOVERY` feature flag.
Enable via `SHOPWARE_AGENTIC_DISCOVERY=1` or
`bin/console feature:enable AGENTIC_DISCOVERY`.

See ADR `adr/2026-05-26-agentic-discovery-endpoints.md` for the placement
rationale and the extension-point contract.

## Architecture

```
AI Crawler (GPTBot / ClaudeBot / PerplexityBot / custom UCP agent)
    |
    v
+---------------------------------------------------------------+
|   AgenticDiscoveryController (Storefront)                     |
|   - feature flag check -> 404 if off                          |
|   - rate limiter (60 req / 60s per IP)                        |
|   - marks request for the response cache subscriber           |
+---------------------------------------------------------------+
    |
    v
+---------------------------------------------------------------+
|   AgenticDiscoveryPageLoader (Storefront)                     |
|   - dispatches AgenticDiscoveryPageLoadedEvent (extension)    |
+---------------------------------------------------------------+
    |
    v
+---------------------------------------------------------------+
|   AgenticManifestBuilder (Core/Framework/AgenticDiscovery)    |
|   - AgenticDiscoveryDomainResolver  (storefront-only)         |
|   - AgenticDiscoveryConfigProvider  (memoized per request)    |
|   - SystemConfigService             (basicInformation.*)      |
|   - iterable<DiscoverySectionProvider>  <-- STABLE EXT POINT  |
|   - sanitizeMerchantText()  (XSS-strips merchant input)       |
+---------------------------------------------------------------+
    |
    v
+---------------------------------------------------------------+
|   Twig (storefront/page/agentic-discovery/*.twig)             |
|   Every section is a named {% block %} for theme override     |
+---------------------------------------------------------------+
    |
    v
+---------------------------------------------------------------+
|   AgenticDiscoveryCacheSubscriber (priority -2000)            |
|   - Overrides Shopware's default "private" cache policy       |
|   - Cache-Control: public, max-age=300, s-maxage=3600, SWR=60 |
|   - Vary: Host; strips Set-Cookie                             |
|   - sw-invalidation-states: agentic_discovery_{scId}          |
+---------------------------------------------------------------+
```

## Folder structure

- `AgenticDiscoveryDocumentType.php` — PHP enum identifying the four documents
- `DataAbstractionLayer/` — Entity definition, entity, collection,
  reverse-association extension on `SalesChannelDefinition`
- `Discovery/` — Domain resolver, config provider, cache-invalidation subscriber
- `Manifest/` — `AgenticManifest` view-model, `AgenticManifestBuilder`,
  `AgenticDiscoveryContext`, `DiscoverySection`, **`DiscoverySectionProvider`** (stable)

The HTTP-level pieces live in the Storefront bundle:
- `Storefront/Controller/AgenticDiscoveryController.php` — four `[Route]` methods
- `Storefront/Page/AgenticDiscovery/{Page,PageLoader,PageLoadedEvent}.php`
- `Storefront/Framework/Cache/AgenticDiscoveryCacheSubscriber.php`
- `Storefront/Resources/views/storefront/page/agentic-discovery/*.twig` — four templates

## Extension points

Exactly one **stable** contract reserved from the first release:

### `DiscoverySectionProvider` (interface + DI tag)

Plugins implement `Shopware\Core\Framework\AgenticDiscovery\Manifest\DiscoverySectionProvider`
and tag the service `agentic_discovery.section`:

```xml
<service id="MyVendor\MyPlugin\LoyaltyDiscoverySection">
    <argument type="service" id="MyVendor\MyPlugin\Service\LoyaltyProgramService"/>
    <tag name="agentic_discovery.section"/>
</service>
```

The provider receives an `AgenticDiscoveryContext` (resolved
`SalesChannelDomainEntity`, the per-channel config row, and the
`Context`) and may return a `DiscoverySection` per
`AgenticDiscoveryDocumentType`. Sections are sorted by descending
`priority` before render.

All other classes in this module carry `@internal` and are subject to
change while the feature flag is experimental.

## Conventions

- All classes use `@experimental stableVersion:v6.8.0 feature:AGENTIC_DISCOVERY`
  annotation
- All classes use `#[Package('framework')]` attribute
- All routes are gated by `Feature::isActive('AGENTIC_DISCOVERY')` in the
  controller; returning HTTP 404 when off so probing agents see a clean signal
- Merchant-provided text (`customIntro`, `customAgentRules`, `customSections`)
  is XSS-sanitized at render time, not at write time — see
  `AgenticManifestBuilder::sanitizeMerchantText()`
- Sales-channel binding via 1:1 association `agenticDiscoveryConfig` on
  `SalesChannelDefinition` (admin-API-only, `CascadeDelete`)
- Cache tag format: `agentic_discovery_{salesChannelId}` — emitted via
  the `sw-invalidation-states` response header

## Security

- HTTP cache headers explicitly set after Shopware's `CacheResponseSubscriber`
  to guarantee reverse-proxy cacheability of these public documents
- `Set-Cookie` stripped from responses so reverse proxies keep one shared
  cache entry per `Host`
- `RateLimiter::AGENTIC_DISCOVERY` (60 req/min sliding window per client IP)
  applies to cache misses only
- Merchant input is sanitized against `<script>`, `<iframe>`, `<object>`,
  event-handler attributes and `javascript:`/`vbscript:`/`data:text/html`
  URI schemes inside Markdown link targets; Markdown formatting itself
  is preserved
- Length-capped at 4000 chars per merchant text field to prevent DoS
  via giant documents

## Future ideas / backlog

- **Merchant identity manifest** — extend the per-channel config with
  brand voice / positioning / hard recommendation policies, exposed as a
  validatable JSON at `/.well-known/agentic-commerce.json`
- **Truth Contract + signed snapshots** — sign prices/inventory/totals
  with the UCP signing key so agents can cite "valid until" guarantees
- **Semantic Product Graph** — eigen entity + indexer + AI worker for
  agent-friendly product metadata
- **`/.well-known/acp/manifest.json`** — optional plugin if the Stripe
  Agentic Commerce Protocol gains significant adoption
