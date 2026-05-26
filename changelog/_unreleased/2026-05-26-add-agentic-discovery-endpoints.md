---
title: Add native Agentic Discovery endpoints (/agents.md, /llms.txt, /llms-full.txt, /sitemap_agentic_discovery.xml)
issue: NEXT-AGENTIC-DISCOVERY
flag: AGENTIC_DISCOVERY
author: Stefan Hamann
author_email: sth@shopware.de
author_github: sthamann
---
# Core
* Added `AGENTIC_DISCOVERY` feature flag (default off, experimental, toggleable) to enable the new Agentic Discovery layer.
* Added new DAL entity `agentic_discovery_sales_channel_config` (definition, entity, collection, sales-channel extension and migration `Migration1779700000AddAgenticDiscoverySalesChannelConfig`) that stores per-sales-channel discovery configuration (active/expose toggles, optional merchant intro, custom agent rules and custom Markdown sections).
* Added `Shopware\Core\Framework\AgenticDiscovery\Manifest\AgenticManifestBuilder` plus stable extension point `DiscoverySectionProvider` and DI tag `agentic_discovery.section` for plugins to contribute Markdown sections to any discovery document.
* Added `Shopware\Core\Framework\AgenticDiscovery\Discovery\AgenticDiscoveryDomainResolver` and `AgenticDiscoveryConfigProvider` for storefront-scoped resolution.
* Added new XML loader `agentic-discovery.xml` in `Core/Framework/DependencyInjection/`, wired from `Framework::build()` after `ucp.xml`.

# Storefront
* Added `Shopware\Storefront\Controller\AgenticDiscoveryController` with four routes scoped to the storefront and HTTP-cached at the reverse proxy:
  * `GET /agents.md` (`text/markdown`) — `frontend.agentic_discovery.agents_md`
  * `GET /llms.txt` (`text/markdown`) — `frontend.agentic_discovery.llms_txt`
  * `GET /llms-full.txt` (`text/markdown`) — `frontend.agentic_discovery.llms_full_txt`
  * `GET /sitemap_agentic_discovery.xml` (`application/xml`) — `frontend.agentic_discovery.sitemap`
* Added storefront pages and templates under `Storefront/Page/AgenticDiscovery/` and `Storefront/Resources/views/storefront/page/agentic-discovery/` with extension-friendly `{% block %}` structure for themes.
* Added `AgenticDiscoveryPageLoadedEvent` so plugins can mutate the page before rendering.
* Extended `storefront/page/sitemap/sitemap.xml.twig` to advertise `/sitemap_agentic_discovery.xml` in the standard sitemap index when `AGENTIC_DISCOVERY` is active.

# Administration
* Added new component `sw-agentic-discovery-config` in the `sw-sales-channel` module. Appears as a card in the "Basic information" tab for Storefront sales channels when the flag is active. Lets merchants toggle each discovery document independently, add a free-form merchant intro and preview the rendered files in a new tab.
* Added `agentic_discovery_sales_channel_config:read|create|update|delete` privileges to the existing `sales_channel.viewer`/`editor` ACL roles.
* Added matching `en` and `de` snippets under `sw-sales-channel.detail.agenticDiscovery.*`.

# Hosting & Configuration
* Added new rate-limiter `agentic_discovery` (`shopware.yaml`) with a sliding-window policy of 60 requests/60 seconds applied to the four discovery routes. Hosts can override the limits via `shopware.yaml` if their crawler traffic profile justifies a different cap.
* Reverse proxies (Varnish, Cloudflare, Fastly) that honour the `sw-invalidation-states` response header receive the cache tag `agentic_discovery_{salesChannelId}` so a single merchant edit invalidates only the affected sales-channel's discovery documents.
