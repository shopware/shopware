---
title: Native Agentic Discovery endpoints (/agents.md, /llms.txt, /llms-full.txt, /sitemap_agentic_discovery.xml)
date: 2026-05-26
area: framework
tags: [framework, agentic-commerce, discovery, llm, ucp, storefront, ai]
authors: [Stefan Hamann]
status: accepted
---

## Context

In May 2026, Shopify rolled out four discovery files natively to every storefront:
`/llms.txt`, `/llms-full.txt`, `/agents.md` and `/sitemap_agentic_discovery.xml`.
The de-facto convention emerging from that rollout — combined with the proposed
`llms.txt` spec from Answer.AI and the joint Google/Shopify Universal Commerce
Protocol — is that AI shopping agents resolve a small fixed set of well-known
paths on a storefront domain to learn how to *interact* with the shop
programmatically.

Shopware ships an experimental UCP server foundation in 6.7.11.0 behind the
`UCP_SERVER` flag (`Core/Framework/Ucp/`, see ADR
`2026-05-20-ucp-feature-flag-and-bundle-placement.md`). UCP defines *what* a
storefront can technically expose (capabilities, transports, signing) — but
agents arriving on a storefront still need to learn the **operational
contract**: which flow to follow, which rules to respect, which endpoints to
use. The Markdown discovery files cover exactly that surface, on top of (and
independent of) UCP.

Existing "Agentic Commerce" code in Shopware is a separate sales-channel **type**
that emits outbound product feeds for OpenAI/ChatGPT Catalog. The naming
overlap is real but the responsibility is the opposite (outbound feed vs.
inbound discovery), so the new code is named **Agentic Discovery** and lives
in `Core/Framework/AgenticDiscovery/`.

The user-facing AGENTS.md convention used by coding agents (e.g. Shopware's own
`AGENTS.md` for developer-side AI assistance) collides with the merchant-facing
`/agents.md` URL. We keep the public path because the market has standardised
on it, but use internal names like `AgenticManifest*` to avoid ambiguity in
code review and IDE search.

## Decision

1. **Add a new feature flag `AGENTIC_DISCOVERY`** (default off, toggleable,
   experimental, sibling to `UCP_SERVER`).

2. **Place the framework code under `Core/Framework/AgenticDiscovery/`**, sibling
   to `Core/Framework/Ucp/`. The HTTP layer lives in
   `Storefront/Controller/AgenticDiscoveryController.php` and
   `Storefront/Page/AgenticDiscovery/` — closer to the Robots and Sitemap
   controllers it conceptually resembles. UCP keeps its discovery controller in
   `Core/Framework/Ucp/Discovery/` because the bundle is self-contained;
   AgenticDiscovery deliberately leans on Storefront's
   `StorefrontController::render()`, route-scope and HTTP-cache infrastructure
   instead of duplicating it.

3. **Bind configuration per `SalesChannel`** in a new entity
   `agentic_discovery_sales_channel_config` (1:1 to `sales_channel`, JSON columns
   for list/map data). The reverse association `agenticDiscoveryConfig` is
   added on `SalesChannelDefinition` via an `EntityExtension`. This mirrors the
   UCP pattern exactly.

4. **Resolve the request domain via a dedicated `AgenticDiscoveryDomainResolver`**
   restricted to `Defaults::SALES_CHANNEL_TYPE_STOREFRONT`. We deliberately
   duplicate ~30 LoC instead of re-using UCP's `SalesChannelDomainResolver`
   because the UCP class is `@internal` and the storefront-only filter is a
   semantic constraint we want to own.

5. **Render through Twig templates** under
   `Storefront/Resources/views/storefront/page/agentic-discovery/*.twig` with
   nested `{% block %}` markers around every logical section. Themes override
   sections individually via `sw_extends`; plugins contribute additional
   sections by tagging a service `agentic_discovery.section` and implementing
   `DiscoverySectionProvider`. Both extension paths coexist and compose.

6. **Reserve a single public extension point** from this PR on:
   `Shopware\Core\Framework\AgenticDiscovery\Manifest\DiscoverySectionProvider`.
   The interface receives an `AgenticDiscoveryContext` carrying the resolved
   `SalesChannelDomainEntity`, the config entity and a `Context`. All other
   AgenticDiscovery classes are `@internal` while the flag is experimental.

7. **Reference UCP soft, not hard.** When `UCP_SERVER` is active the manifest
   builder includes the `/.well-known/ucp` URL in its endpoint list and the
   `## UCP` section in `/llms-full.txt`. When `UCP_SERVER` is off the documents
   still serve, but reference Store API paths exclusively. We avoid a hard
   compile-time dependency on UCP classes so the AgenticDiscovery feature can
   eventually be enabled independently.

8. **Cache aggressively at the reverse proxy.** All four routes set
   `PlatformRequest::ATTRIBUTE_HTTP_CACHE => true`. Content changes are gated
   by the `agentic_discovery_sales_channel_config` row, so cache invalidation
   piggy-backs on that entity write.

9. **Default the feature off, default the per-channel configuration on.** Once
   a merchant enables the flag, every storefront sales channel automatically
   serves all four documents unless the channel-level config row toggles them
   off. This mirrors the Shopify behaviour where storefronts get all four files
   without explicit configuration but lets merchants opt out per channel.

## Why not …

- **Why not reuse the Shopware "Agentic Commerce" sales-channel type?**
  Because that type is an *outbound feed producer* (product JSONL for AI
  catalog ingestion). Discovery is *inbound metadata served from the storefront
  domain*. Both should coexist; reusing the same code path would conflate
  unrelated concerns.

- **Why not put everything in UCP?**
  Because the four discovery files are a Markdown-first, human-readable layer
  that intentionally outlives UCP version churn. Tying them to `UCP_SERVER`
  would mean merchants who only want llms.txt/agents.md cannot have them
  without enabling the much larger UCP feature surface.

- **Why not store the discovery content as Liquid-/Twig-template overrides like
  Shopify?**
  Shopware already provides theme-level Twig override via `sw_extends`. Adding
  a second override channel (e.g. a `template`-typed system config or a
  database-stored template) would duplicate the inheritance system. Instead,
  per-channel JSON fields (`custom_intro`, `custom_agent_rules`,
  `custom_sections`) cover ad-hoc merchant copy without requiring theme code;
  deep layout changes go through the standard theme override mechanism.

- **Why not a separate `sw-settings-agentic-discovery` Admin module?**
  Because configuration is per sales channel and tightly coupled to the
  channel's domains, language and currency. Surfacing it as a card in the
  existing channel detail flow keeps the merchant context together.

## Consequences

- **Pro:** Shopware storefronts become natively discoverable by UCP- and
  llms.txt-aware agents without merchant-side configuration after enabling
  the flag.
- **Pro:** Single canonical extension point (`DiscoverySectionProvider`) for
  plugins; no risk of fragmented per-document hooks.
- **Pro:** Themes override via the same Twig inheritance they already use
  everywhere else; no new templating layer.
- **Con:** A second @experimental feature flag adds operational complexity;
  the rollout plan should flip `AGENTIC_DISCOVERY` on together with
  `UCP_SERVER` in 6.8.0.
- **Con:** `AgenticDiscoveryDomainResolver` duplicates ~30 LoC of UCP's
  resolver. A future refactor may extract a shared
  `Framework\Routing\PublicDomainResolver`.

## References

- Companion PR: UCP server foundation (`feat: Introduce Universal Commerce
  Protocol (UCP) server foundation`).
- ADR `2026-05-20-ucp-feature-flag-and-bundle-placement.md`.
- llms.txt specification (Answer.AI, Sep 2024): https://llmstxt.org/
- UCP specification: https://ucp.dev/
- Shopify Agentic Storefronts rollout (May 2026, third-party documentation):
  Craftshift "Shopify quietly shipped native llms.txt, agents.md, UCP
  discovery, and an agentic sitemap to every store".
