# MCP: current state & roadmap

Planning reference for contributors and PMs. For the public docs see [developer.shopware.com/docs](https://developer.shopware.com/docs/). For the in-repo contributor index see [README.md](README.md).

**Horizon legend:** `Done` = shipped on this branch. `V1` = pre-SCD target. `V2` = post-SCD. `Later` = no active owner yet.

**Effort:** `S` ~1–3 days · `M` ~1–2 weeks · `L` ~3–6 weeks · `XL` multiple months.

---

## Epic overview

### V1 (pre-SCD) — target

| Theme | Outcome |
|-------|---------|
| **Core platform** | `/api/_mcp` GA-hardened: structured observability on every tool call, per-integration allowlist with empty default on new integrations, feature flag lifecycle decision |
| **Merchant plugin** | All `merchant-*` tools in `SwagMcpMerchantAssistant` (done); core keeps primitives only |
| **SwagMcpDevTools MVP** | Log streaming + search shipped (done); declared installable |
| **Public docs** | First slice on developer.shopware.com shipped (done); in-repo kept as contributor reference |
| **Official samples** | `McpHelloWorld` + `SwagMcpAdminUsers` moved to `shopware/*` org, polished, linked from docs |

### V2 (post-SCD) — planned

| Theme | Outcome |
|-------|---------|
| **SwagMcpDevTools depth** | System/health probes, deprecation/version context, migration status, log–request correlation |
| **Per-user MCP allowlist** | Per-user allowlist on `user`; bearer JWT re-enabled; Copilot intersection implemented. See [gaps-user-allowlist.md](gaps-user-allowlist.md) |
| **Docs depth** | Exhaustive reference, automation, full site chapter on developer.shopware.com |
| **Analytics products** | In-admin dashboards, SIEM packs (requires V1 structured observability baseline) |
| **Store API MCP** | Customer-authenticated MCP surface for buyer-journey tools (browsing, cart, checkout). Needs PM driver |
| **WebMCP (watch)** | Browser-native tool exposure for human-in-loop storefront UX. W3C CG spec, no browser ships it yet |

---

## Workstream 1: Core MCP platform

| Item | Status | Notes | Effort | Horizon |
|------|--------|-------|--------|---------|
| `/api/_mcp` endpoint, Streamable HTTP | **Done** | `McpServerController` | S | — |
| Tool / prompt / resource discovery (core + bundles + apps) | **Done** | `McpToolCompilerPass`, `McpCapabilityDiscoveryTest` | S | — |
| App registration (XML, persistence, HMAC execution) | **Done** | `AppMcpToolLoader` / `AppMcpToolExecutor` | M | — |
| Auth: Admin API bearer + integration header | **Done** | `McpAuthenticationListener` | S | — |
| Rate limiting | **Done** | `RateLimiter::MCP` + OAuth bucket | S | — |
| Per-installation allowlist | **Done** | `shopware.mcp.allowed_tools` + compiler pass | S | — |
| Per-integration allowlist (Admin UI + API + runtime filter) | **Done** | `integration.mcp_allowlist`, `McpAllowlistProvider`, `sw-integration-mcp-allowlist` component | M–L | — |
| Write safety (dry-run defaults) | **Done** | All write tools default `dryRun=true` | S | — |
| Naming, conflict detection, error contracts | **Done** | Compiler pass conflict detection; `McpToolResponse` | S | — |
| ACL on tools | **Partial** | Most tools ACL-gated; `entity-schema` and resources explicitly no ACL | M | V1 |
| **Structured MCP observability** (telemetry on every tool call) | **Partial** | `mcp` Monolog channel exists; no OpenTelemetry spans or metrics emission yet. Needed for adoption data and tool census | M | **V1 (GA blocker)** |
| Feature flag `MCP_SERVER` lifecycle | **Partial** | Good for POC; needs lifecycle decision (default on, compile-time removal path) | S | V1 |
| New integrations start with empty allowlist (enforcement) | **Open** | Current default is `NULL` (unrestricted); product direction says new integrations start with no tools selected | S | V1 |
| **Per-user MCP allowlist** (bearer token + Copilot intersection) | **Done** | Per-user allowlist on `user`; bearer JWT re-enabled; Copilot intersection via `sw-app-user-id`. See [gaps-user-allowlist.md](gaps-user-allowlist.md) | M | — |
| ACL on read-only resources | **Open** | Resources are reference data today; only if security review demands it | M | Later |
| Optional discovery metadata | **Open** | Not needed if Admin allowlist + docs are sufficient | M | Later |
| Phase 2: MCP analytics (dashboards, SIEM) | **Open** | Requires structured observability baseline first | L | V2 |

---

## Workstream 2: SwagMcpDevTools

Remote-instance developer introspection via `/api/_mcp`. Fills the gap that [ai-coding-tools](https://github.com/shopwareLabs/ai-coding-tools) (laptop-local) cannot cover. Tracking: [#16205](https://github.com/shopware/shopware/issues/16205).

| Item | Status | Notes | Effort | Horizon |
|------|--------|-------|--------|---------|
| Bundle in `custom/bundles/SwagMcpDevTools/` | **Done** | Reuses `/api/_mcp`, integration auth, ACL, rate limiter | M | — |
| Log streaming tool (`swag-dev-tools-log-stream`) | **Done** | Filter by channel/level/time; sensitive-field redaction | M | — |
| Log search tool (`swag-dev-tools-log-search`) | **Done** | Query by message pattern / correlation ID | S–M | — |
| Notifications tool (`swag-dev-tools-notifications`) | **Done** | Polls/SSE-waits for Shopware notifications; `NotificationEventSubscriber` fires on indexer + import-export completion | S | — |
| Developer-grade ACL | **Done** | Integration role mapping for dev tools | S | — |
| System / health probes (queue, tasks, cache, feature flags) | **Open** | Read-only; V2 | M | V2 |
| Deprecation + version context tool | **Open** | Useful for upgrade agents | S–M | V2 |
| Migration status tool | **Open** | Read-only | S | V2 |
| Log ↔ request correlation | **Open** | Requires structured observability schema | M | V2 |
| Optional laptop-side metapackage + editor templates | **Open** | Only if measurable demand; companion to Labs, not a second server | S–M | Later |

---

## Workstream 3: Merchant plugin

| Item | Status | Notes | Effort | Horizon |
|------|--------|-------|--------|---------|
| All `merchant-*` tools moved out of core | **Done** | `custom/plugins/SwagMcpMerchantAssistant/` — 9 tools; zero merchant tools remain in `src/Core/` | L | — |
| Core keeps only platform primitives | **Done** | Per [ADR](../../../../../adr/2026-03-17-mcp-server-placement-and-extensibility.md) | — | — |
| Human-readable tool matrix (core vs plugin) | **Partial** | Keep aligned with ADR as tools evolve | S | V1 |

---

## Workstream 4: Developer documentation

First public slice shipped via `shopware/docs#2264`. In-repo `docs/` is now the contributor reference; canonical content is on developer.shopware.com.

| Item | Status | Notes | Effort | Horizon |
|------|--------|-------|--------|---------|
| First public slice on developer.shopware.com | **Done** | Overview, setup, security, extensibility, examples | M | — |
| Extension guide (plugins, bundles, apps) | **Partial** | Base content ported; polish + expansion needed | M | V1 |
| Architecture: core vs dev bundle vs external | **Partial** | `agent-user-stories.md` + ADR narrative; needs public "Concepts" page | S–M | V1 |
| `ai-coding-tools` companion story (local vs remote) | **Partial** | Short in-repo stub exists; public page pending | S–M | V1 |
| In-repo pointers to canonical URLs | **Open** | Add after public pages are stable; avoid two sources of truth | S | After V1 docs |
| Full docs expansion (exhaustive reference, automation) | **Open** | Post-SCD depth | L | V2 |
| Editor-specific best practices | **Open** | Cursor, Claude Desktop etc. | S | Later |

---

## Workstream 5 + 6: Sample apps

| Item | Status | Notes | Effort | Horizon |
|------|--------|-------|--------|---------|
| `McpHelloWorld` app in-repo (`custom/apps/McpHelloWorld/`) | **Done** | Minimal app — manifest, server.js, 3 tools | S | — |
| `SwagMcpAdminUsers` plugin in-repo | **Done** | ACL-aware plugin with 2 tools + 2 resources | S | — |
| Move both to `shopware/*` org + polish + CI | **Open** | Canonical URLs; update all doc links after move | S–M | V1 |
| Non-trivial example tool / richer prompts + resources | **Open** | Extend after org move | M | Later |

---

## Cross-cutting: Observability & operations

| Item | Status | Effort | Horizon |
|------|--------|--------|---------|
| **Structured MCP observability** (see Workstream 1) | **Partial** | M | **V1 (GA blocker)** |
| CI jobs and changelog for MCP flag | **Partial** | M | V1 |
| Support playbook (integration permissions, allowlists) | **Open** | S | V1 |
| Load and abuse testing | **Open** | M | Later |
| Quarterly "tool census" from telemetry | **Open** | S | Later (process, not code) |

---

## MCP spec coverage (2025-11-25 server)

Contributor reference: [spec-coverage.md](spec-coverage.md) · Spec: [modelcontextprotocol.io/specification/2025-11-25/server](https://modelcontextprotocol.io/specification/2025-11-25/server)

Shopware uses **Streamable HTTP** at `/api/_mcp` via `symfony/mcp-bundle`. Session init and JSON-RPC routing are delegated to the bundle/SDK. Shopware adds: Admin API auth bridge, rate limits, feature flag, `McpContextProvider`, app HMAC execution, `McpToolCompilerPass`.

| Spec topic | Shopware today | Gap / follow-up |
|------------|----------------|-----------------|
| **Tools** | Many in-process + app tools; `#[McpTool]`; `McpCapabilityDiscoveryTest` | No extra discovery metadata in V1; revisit if clients need more than allowlist + docs |
| **Prompts** | `shopware-context` + app-backed prompts loader | Optional extra prompts; keep discovery test aligned |
| **Resources** | 8 static resources + `shopware://tool-result/{id}` template (large-result delivery) | Templates/subscriptions if clients rely on them; ACL policy still open |
| **Completion** | Unknown — likely partially handled by `symfony/mcp-bundle` | Spike: wire entity name / field / enum completions for `shopware-entity-search` |
| **Logging** | `mcp` Monolog channel (debug/support); product metrics need OpenTelemetry path | Decide on `logging/setLevel` + `notifications/message` as real protocol feature |
| **Pagination** | Application-level (`_meta`, criteria `page`/`limit`) | Confirm if protocol-level `resources/list` cursors are needed |
| **Client: Roots / Sampling / Elicitation** | N/A on server side | Document "N/A on `/api/_mcp`" in public docs |

**Shopware-specific gaps vs bundle:**
1. Domain completions (entity names, state machine states, sales channel ids) need custom handlers.
2. Capability advertisement in `initialize` must stay honest after bundle upgrades — run spec checklist in CI.

---

## Quick gap list (current state)

**Shipped / done**

- `/api/_mcp` endpoint and Streamable HTTP.
- Discovery across Core, Storefront bundle tools, and plugin attribute path.
- Apps: XML, DB, signed remote tools (`McpHelloWorld`, `SwagMcpAdminUsers` in-repo).
- ACL + dry-run + per-integration allowlist + rate limit + conflict detection.
- **`SwagMcpDevTools` MVP** — log streaming, log search, notifications tool (indexer/import-export events via SSE). Lives in `custom/bundles/SwagMcpDevTools/`.
- **Merchant workflows out of core** — all 9 `merchant-*` tools in `custom/plugins/SwagMcpMerchantAssistant/`; zero merchant tools remain in `src/Core/`.
- **Per-user MCP allowlist** — `user.mcp_allowlist`; bearer JWT re-enabled; Copilot `sw-app-user-id` intersection implemented. See [gaps-user-allowlist.md](gaps-user-allowlist.md).
- **Public docs first cut** — shipped via `shopware/docs#2264`; in-repo `docs/` is now contributor reference only.
- **Reference apps** — `custom/apps/McpHelloWorld/` and `custom/plugins/SwagMcpAdminUsers/` exist in-repo (org move to `shopware/*` still pending).

**Still open**

- **Structured MCP observability** — only `mcp` Monolog channel today. No OpenTelemetry spans, no metrics emission on tool calls. Needed to prove adoption, detect zero-use tools, and judge quality.
- **Per-integration allowlist — new integrations default empty** — current default is `NULL` (unrestricted). Product direction says new integrations start with no tools selected; enforcement not yet in place.
- **`shopware/*` org move for samples** — `McpHelloWorld` and `SwagMcpAdminUsers` are in-repo on this branch; need move + polish + canonical docs links.
- Optional ACL on resources (if security review demands it).
- Optional discovery metadata (deferred; revisit only if allowlist + docs prove insufficient).

**Future roadmap (needs PM ownership)**

- **Store API MCP server** — customer-authenticated MCP surface (session token / customer credentials) scoped to the buyer journey: browsing, cart, checkout, account. Enables AI shopping assistants acting *as the shopper*, not as a merchant operator. Separate endpoint (e.g. `store-api/_mcp`), auth, scope, rate limiting, tool set. Raised internally — needs PM driver.
- **WebMCP (browser-native, Storefront)** — W3C WebMachinelearning CG proposal (Aug 2025, Microsoft) for browser-native JS tools exposed to in-browser agents. Human-in-loop storefront workflows only; explicitly **not** for autonomous agents (that use case = Store API MCP). No browser ships it yet. See: https://github.com/webmachinelearning/webmcp

---

## Suggested parent epics

| Epic | Workstreams | Key open items |
|------|-------------|----------------|
| **A — MCP platform (core)** | WS1, Cross-cutting | Structured observability (GA blocker), empty allowlist default, feature flag lifecycle, ACL gaps |
| **B — MCP extensions** | WS2 (V2 depth), WS3, WS5, WS6 | `shopware/*` org move for samples; SwagMcpDevTools V2 probes |
| **C — MCP documentation** | WS4 | Extension guide polish, companion story, in-repo pointers after V1 docs stable |
| **D — V2 roadmap** | — | Per-user allowlist, Store API MCP, analytics products, docs depth |

*Related in-repo docs: [README.md](README.md) · [spec-coverage.md](spec-coverage.md) · [agent-user-stories.md](agent-user-stories.md) · [gaps-user-allowlist.md](gaps-user-allowlist.md)*
