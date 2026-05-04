# MCP: POC to product (epic backlog)

This document maps upper-level roadmap expectations (six modules) to the current state on the MCP POC branch and proposes work in a sensible delivery order.

**Need a GitHub Epic scope, not a seminar?** Start with [Epic V1 vs V2 (around SCD)](#epic-v1-vs-v2-around-scd-github-ready-scope). Everything below is **reference**: vocabulary, trade-offs, workstream tables, and spec checklists.

### Alignment with `delivery-process/issues-and-labels.md`

Shopware’s **Epic** issue type is for work that typically spans **about 1–3 months** and should be **broken into child issues** with `domain/` and `priority/` labels. This file is a **planning aid**, not a commitment to open one GitHub Epic per row.

### Epics vs sub-issues (wording)

The **six roadmap boxes** in leadership’s picture are **themes / workstreams**, not a requirement to open **six** tracking epics.

In this file, **“Workstream 1 … 6”** sections are a **decomposition of those themes**. In GitHub you would usually create **a small number of parent Epics** (often **2–4** per the delivery guide) and turn each **row in the tables** into **Stories** or **Technical TODOs** (child / sub-issues).

### Effort column (how to read it)

The **S / M / L / XL** tags are **rough calendar risk for one senior engineer**, mainly **implementation + tests**. They are **not** official estimates and they are **not** aligned to a story-point system (Shopware’s issue doc does not define one).

**AI assistance:** Expect the biggest speedup on **boilerplate** (tests, snippets, docs drafts, repetitive refactors). Expect **little or no** shortcut on **security review, cross-bundle moves, API contract decisions, and CI or release coordination**. After AI drafts, **human review** often becomes the bottleneck.

**Early May deadline:** From mid-April, “beginning of May” is roughly **one to two weeks of calendar**. Treat anything **L / XL** or cross-team as **defer** unless leadership explicitly cuts scope elsewhere.

**Effort legend**

| Tag | Meaning (rough, one engineer, implementation-heavy) |
|-----|-----------------------------------------------------|
| **S** | About 1–3 days |
| **M** | About 1–2 weeks |
| **L** | About 3–6 weeks |
| **XL** | Multiple months or needs parallel workstreams |

**Milestone legend** (for “beginning of May” delivery conversation)

| Tag | Meaning |
|-----|---------|
| **May** | Realistic to **target or ship** if this is the top priority for the team |
| **Maybe** | Small slice only, or needs scope cut / parallel owner |
| **Later** | **Defer** past the May milestone (still valuable, not for this date) |

**Status legend**

| Tag | Meaning |
|-----|---------|
| **Done (POC)** | Implemented in this branch in a form close to product needs |
| **Partial** | Exists but needs hardening, split, or product polish |
| **Open** | Not started or only sketched |

---

## Epic V1 vs V2 (around SCD): GitHub-ready scope

**SCD** here means **Shopware Community Day** (the next public conference milestone). Dates move each year; treat **V1 = before SCD** and **V2 = after SCD** as **planning horizons**, not magic dates.

Use **one parent Epic for V1** (or split **Platform** vs **Docs** if your team prefers two epics under the same umbrella). Use **V2** as a **second Epic** or a **milestone label** so roadmap slides do not imply everything ships in one quarter.

### Epic V1 (before SCD): supported shop HTTP MCP

**One-line outcome:** **`/api/_mcp` is the priority:** **safe**, **observable**, **slim core** (merchant-facing workflow tools live in an **installable plugin** per [ADR MCP placement](../../../../../adr/2026-03-17-mcp-server-placement-and-extensibility.md)), **documented on developer.shopware.com**, and **operator-controlled** via **Admin MCP tool selection per integration** with **no tools selected by default** on new integrations (see [tool selection on the integration](#can-mcp-tool-selection-live-on-the-integration-itself)). **Official** sample App + sample plugin under **`shopware` org**, **polished**, linked from canonical docs. **Local dev MCP** ([ai-coding-tools](https://github.com/shopwareLabs/ai-coding-tools)) is **referenced only** in V1. **No** extra discovery metadata work in V1.

| Theme | What to ship (child issues) | Explicitly **out** of V1 |
|-------|-----------------------------|---------------------------|
| **Platform** | Finish **[structured MCP observability](#mcp-observability-day-1-requirement)** on every tool call (and prompts/resources where relevant). Decide **feature flag** lifecycle. **Spike** [spec vs bundle](#mcp-spec-coverage-2025-11-25-server-vs-shopware) (Completion / Logging / Pagination truth table). Harden **security follow-ups** from review (ACL gaps only if blocking). | Extra discovery metadata work. Separate discovery shaping projects beyond Admin tool selection per integration. |
| **Merchant MCP plugin (ADR)** | **Move** opinionated merchant / assistant workflow tools **out of core** into a **plugin or bundle** so core keeps **platform primitives** only ([Workstream 3](#workstream-3-merchant-oriented-capability-plugin-split-from-core), [ADR “Placement model”](../../../../../adr/2026-03-17-mcp-server-placement-and-extensibility.md#placement-model)). Same `/api/_mcp` discovery; tools register via existing extension contracts. **Bounded inventory:** follow ADR “Move to plugin” lists; timebox risky moves if needed. | Second merchant product line, speculative workflows not in the ADR inventory. |
| **Admin: MCP per integration** | Tool selection (**allowlist**) on the **integration** — persistence, API, UI; filter `tools/list` and `tools/call` **with** ACL ([design](#can-mcp-tool-selection-live-on-the-integration-itself)). **New integrations start empty** and admins explicitly select tools. | Replacing ACL with allowlists; granting privileges the integration’s **roles** do not have. |
| **Public docs (pre-SCD)** | **First** [developer.shopware.com/docs](https://developer.shopware.com/docs/) MCP section: **extract + polish** from `src/Core/Framework/Mcp/docs/` (overview, setup, security, extensibility, examples, tool summary). **One clear paragraph** that shop HTTP MCP ≠ local dev MCP, with a **link to [ai-coding-tools](https://github.com/shopwareLabs/ai-coding-tools)**. For **agent doc lookup**, point integrators to **Context7** (or the same via their editor stack), not a shop-hosted “search docs” MCP tool ([see Concepts](#developer-documentation-in-the-agent-loop-no-shop-mcp-tool-in-v1)). | **Exhaustive** long-form parity, Phase **2** dashboards / SIEM ([Phase 2](#phase-2-dashboards-and-siem-packs)). |
| **Official samples** | **Move + polish** the reference **App** and **Plugin** under the **`shopware` org** (names TBD with legal/releng), align with current core, README, CI light if needed ([Workstream 5](#workstream-5-example-app-remote-mcp-extension), [Workstream 6](#workstream-6-example-plugin-in-process)). Update **all** doc links to the new canonical URLs. | A **second** wave of sample repos, heavy feature growth in samples before core GA story is stable. |
| **Developer MCP bundle (MVP)** | `SwagMcpDevTools` bundle installable via Shopware CLI on SaaS/PaaS/on-prem. **MVP tools:** log streaming + log search (read-only), reusing `/api/_mcp`, integration auth, ACL, rate limiter ([Workstream 2](#workstream-2-developer-mcp-bundle-remote-reachable-developer-tools)). Public docs page clarifies remote-bundle vs laptop-local Labs. | Reimplementing PHPStan / PHPUnit / ECS / JS tools in core (stays in Labs). Any write operations in MVP. System/health probes, deprecation, correlation — **[V2](#epic-v2-after-scd-ecosystem-and-ux-depth)**. |
| **Quality bar** | CI, changelog, release stance aligned with PM. In-repo docs: short **pointers** to canonical **public docs + official sample** URLs ([Workstream 4](#workstream-4-developer-documentation-product-grade)). | Shop-native **“read developer docs”** tools on `/api/_mcp`. Reimplementing PHP/JS tooling in core. Optional local-side metapackage + editor templates — **[V2](#epic-v2-after-scd-ecosystem-and-ux-depth)**. |

**V1 success criteria (pick 3–5 for the Epic description):**

1. Every production-relevant **`tools/call`** emits **structured** telemetry suitable for adoption metrics.  
2. **Security + rate limits** match documented model; known gaps are **listed** with owners.  
3. **Capability story** is honest: documented what MCP **2025-11-25 server** features we rely on the bundle for vs Shopware-only.  
4. A **new integrator** finds **developer.shopware.com** MCP pages sufficient to connect a client (in-repo docs remain for contributors; they should defer to canonical URLs once live).  
5. (Target) An **admin user** can configure **which MCP tools** an integration may expose **without** a deploy-time global list only; new integrations start with **no MCP tools selected** until an admin enables them.
6. (Target) **Sample App + sample plugin** are **under `shopware/*`**, **polished**, and linked from **developer.shopware.com** MCP guides.
7. (Target) **Core MCP surface** matches the **ADR**: primitives + foundation in core; **merchant assistant** tools ship from the **plugin** (or bundle) and are optional to install.

**Suggested Epic title:** *MCP — GA: slim core (ADR), Admin tool selection, public docs, official samples (pre-SCD)*

**Suggested child labels:** `domain/core`, `domain/docs`, `domain/admin`, `domain/releng`, **`domain/plugin`** (merchant MCP extract and `SwagMcpDevTools` bundle) + `priority/` per delivery guide; link children to **Workstreams 1, 2 (MVP), 3, 4, 5, 6**, **spec checklist**. **[Workstream 2](#workstream-2-developer-mcp-bundle-remote-reachable-developer-tools)** V1 scope is the **`SwagMcpDevTools` MVP** (log streaming + search); deeper probes and laptop-side packaging stay **V2**.

### Epic V2 (after SCD): ecosystem and UX depth

**One-line outcome:** **Deeper** merchant plugin evolution, **public docs** expansion, **`SwagMcpDevTools` depth** (system/health probes, deprecation + version context, log-request correlation) on top of the V1 MVP, optional **laptop-side packaging** (metapackage + Cursor/Claude templates) as a Labs companion, and any **optional later** discovery improvements **after** V1 ships **slim core + merchant plugin**, Admin tool selection, `SwagMcpDevTools` MVP, and official samples.

| Theme | Typical content | Depends on |
|-------|-----------------|------------|
| **Architecture** | **Further** splits or hardening on top of the **V1 merchant MCP plugin** ([Workstream 3](#workstream-3-merchant-oriented-capability-plugin-split-from-core)): extra bundles, storefront-only packaging, long-tail workflow polish — **not** redoing the initial ADR extraction if V1 already landed it. | V1 plugin in the wild + telemetry. |
| **Discovery / policy** | Revisit extra discovery metadata only if V1 telemetry shows that Admin tool selection is not enough. | V1 telemetry + how integrators use Admin allowlists in practice. |
| **Docs** | **Expand** the live MCP section on developer.shopware.com: exhaustive tool reference, advanced guides, sidebar IA, release automation, trim agent-only noise with a clear split from in-repo contributor notes ([Workstream 4](#workstream-4-developer-documentation-product-grade)). | Pre-SCD pages published (canonical base). |
| **`SwagMcpDevTools` depth + Labs companion** | [Workstream 2](#workstream-2-developer-mcp-bundle-remote-reachable-developer-tools): extend the V1 bundle with **system / health probes**, **deprecation + version context**, **migration status**, and **log ↔ request correlation** (all read-only, still on `/api/_mcp`). **Laptop-side companion:** optional **Composer metapackage** + **Cursor/Claude** templates pointing at **ai-coding-tools**. Revisit **shop-native “search docs”** tools only if metrics say V1 story is insufficient (default remains **Context7** + Labs). | V1 MVP (`SwagMcpDevTools` log streaming + search) shipped; [day-1 observability](#mcp-observability-day-1-requirement) schema stable. |
| **Observability product** | [Phase 2](#phase-2-dashboards-and-siem-packs): dashboards, SIEM packs. | V1 event schema in production. |
| **Samples / Apps** | **Richer** demos on top of **V1 official** repos: extra prompts/resources, second examples, teaching-only depth ([Workstream 5](#workstream-5-example-app-remote-mcp-extension), [Workstream 6](#workstream-6-example-plugin-in-process)). | Official `shopware/*` samples + docs already live. |

**Suggested Epic title:** *MCP — post-SCD: bundles, Labs depth, docs depth, discovery*  

**Anti-pattern:** Opening **V2** Labs or metadata work **before** **[V1](#epic-v1-before-scd-supported-shop-http-mcp)** lands **observability + frozen ADR tool inventory** (merchant plugin + slim core), or splitting into **six** GitHub Epics that mirror the six roadmap boxes one-to-one.

---

## Concepts (shared vocabulary)

This section explains terms that appear later in the backlog so GitHub issues and leadership slides stay aligned.

### Two MCP surfaces (do not mix them up)

| Surface | Where it runs | Typical auth | Examples |
|---------|----------------|--------------|----------|
| **Shop HTTP MCP** | On the Shopware installation, path `/api/_mcp` | Admin API OAuth or integration keys, ACL on the shop | Entity search, order summary, app tools called back via HMAC |
| **Local editor MCP** | On the developer laptop or CI runner, next to the IDE | Whatever the editor or runner allows | [ai-coding-tools](https://github.com/shopwareLabs/ai-coding-tools) `php-tooling`, `js-admin-tooling`, `js-storefront-tooling` |

Product docs and Epics should **name which surface** they mean. “MCP for Shopware” is ambiguous and causes double work (for example reimplementing PHPStan inside the shop kernel).

### Developer documentation in the agent loop (no shop MCP tool in V1)

Some roadmaps sketch an MCP **tool on the shop** that **searches** or **reads** **developer.shopware.com**. **[Epic V1](#epic-v1-before-scd-supported-shop-http-mcp) explicitly does not** build that: it duplicates concerns that belong on the **agent side** (network access to docs, embeddings, third-party doc APIs).

**Default story for coding agents:** use **Context7** (or the same capability **via** [ai-coding-tools](https://github.com/shopwareLabs/ai-coding-tools) and editor MCP wiring) for **library- and product-doc** answers. Shopware **public MCP docs** explain setup and security; they do not need to be re-exposed as **`/api/_mcp` tools**.

**[Epic V2](#epic-v2-after-scd-ecosystem-and-ux-depth)** can reopen a **first-party** doc-search tool only if adoption data shows a gap Context7 + Labs cannot cover.

### Tool selection and list shaping

For V1 we are **not** adding extra discovery metadata.

The chosen product direction is simpler:

- discovery stays standard MCP discovery with name, description, and schema
- core vs merchant-plugin separation is documented through a human-readable tool matrix
- tool visibility is controlled through **per-integration MCP tool selection in Admin**
- new integrations should start with **no MCP tools selected**
- ACL remains mandatory and is evaluated together with the selected tool list

This gives a deterministic and operator-controlled surface without adding a new metadata contract to discovery in V1.

### Why V1 keeps discovery simple

Extra discovery metadata is still a valid later topic, but it is not needed for the first product cut.

Reasons to defer it:

- it adds new discovery-contract work for core, plugins, and apps
- clients may ignore the metadata anyway
- it does not replace ACL or operator-controlled allowlists
- it is not required to ship a small, safe, supportable HTTP MCP surface

So the V1 rule is:

- **use Admin tool selection per integration**
- **use ACL for authorization**
- **document the tool split clearly**
- **defer discovery metadata unless real client demand appears later**

### MCP observability (day-1 requirement)

**Product requirement from day one:** operators and product need to know **which MCP tools (and later prompts/resources) are actually used**, **how often**, **with what outcomes** (success vs validation vs ACL vs error), and **latency**, so we can **judge quality**, **prioritize docs and UX**, and **remove or demote dead weight** (unused tools, misleading prompts). This is **not** optional “nice analytics later”; it is **telemetry for a new surface area** comparable to shipping a new API.

**Baseline (ship with GA or first public promotion, whichever comes first):**

| Signal | Why it matters |
|--------|----------------|
| **Tool name** (and later prompt/resource id) | Rank usage; find zero-call tools. |
| **Outcome** | `success`, `client_error`, `acl_denied`, `validation_error`, `internal_error` — tells good vs broken vs permission story. |
| **`dryRun` flag** | Separate “preview traffic” from commits. |
| **Duration** | Find slow tools; regressions. |
| **Principal hint** | Stable **integration id** or **hashed** client identifier (avoid logging raw secrets). |
| **Correlation** | Request or MCP session id to tie multi-step flows without logging full payloads. |

**Privacy and supportability:**

- **Do not** log full **tool arguments** or **PII** by default in structured lines. Prefer **hashes**, **lengths**, or **redacted** previews agreed with security.
- Document **retention** and how merchants **export or disable** extended logging if required by policy.

**Implementation shape (preferred):** use Shopware’s **telemetry abstraction layer** for central MCP usage collection, so metrics can flow through the existing OpenTelemetry-capable transport story instead of living only in local logs. The `mcp` Monolog channel should remain as a **support/debug trail**, not as the main product analytics path.

Practical split:

- **Telemetry metrics** for central aggregation: call count, outcome count, dry-run count, latency histograms
- **Structured logs** for request-level support detail where correlation matters

This keeps adoption data comparable across installations while still preserving a local audit trail for debugging.

#### Phase 2: dashboards and SIEM packs

**Later product:** saved reports, in-admin charts, packaged SIEM content, anomaly detection. These still depend on the **same** day-1 events; they are a **presentation layer**, not a substitute for emitting the events early.

### MCP prompts: one today, several tomorrow?

#### What exists today

There is a single registered prompt, **`shopware-context`**, implemented as `ShopwareContextPrompt` in core. It returns one long **instruction block** for the model: DAL and criteria basics, lists **many** tools and resources by name, outcome-tool workflows, dry-run reminders, and error recovery tips. It is intentionally **shop-centric** runtime guidance (see class docblock: distinct from root `AGENTS.md`).

Clients fetch it through normal MCP **prompts** discovery and `prompts/get`.

#### How prompts could evolve

MCP allows **multiple** prompts. Shopware could add more names, for example:

| Prompt idea | What would change in the text |
|-------------|-------------------------------|
| **`shopware-context-minimal`** | Only DAL, criteria, UUID rules, response envelope — **no** long workflow sections (saves tokens, generic integrator). |
| **`shopware-context-merchant`** | Emphasize outcome tools, storefront flows, dry-run discipline; de-emphasize low-level entity-delete patterns. |
| **`shopware-context-platform`** | Target extension authors: naming, `shopware.mcp.tool`, apps, conflict rules; lighter on “create a product” recipes. |

Each prompt is still **plain text instructions**. They do **not** change tool **schemas** or **implementations**. They change what the **model is told to prefer**, how careful to be, and which workflows are spelled out.

#### Important limitation: prompts do not control access

Prompts can shape how cooperative clients behave. They do **not** decide which tools are visible or callable.

The V1 rule is simpler:

- prompts are editorial guidance only
- Admin tool selection determines visibility
- ACL still determines authorization at call time

#### Practical combo (often enough for v1)

0. **[Structured MCP observability](#mcp-observability-day-1-requirement) from day one** (required for product learning, independent of prompts or metadata).
1. **Keep or split prompts** for token budget and tone (cheap, editorial).
2. **Ship Admin tool selection per integration** as the main product-facing control.
3. Keep `shopware.mcp.allowed_tools` as a coarse installation-wide safety switch.
4. Revisit extra discovery metadata later only if clients still need more than (1) + (2) + (3).

#### Maintenance warning

`shopware-context` already **duplicates** knowledge that also lives in the published MCP documentation set. Every additional full prompt **multiplies drift risk** unless you **generate** prompt bodies from a single internal source or keep supplementary prompts short and link out to docs.

### Integration keys, ACL, and tool selection

Shopware already ties **who may do what** to the **authenticated Admin API principal**: an **integration** (access key + secret) or a user token resolved to an `AdminApiSource` with **ACL privileges**. The MCP endpoint **`/api/_mcp` is the same URL for everyone**; the **difference is the credentials** on each request. That is the normal and correct model.

So yes: **differentiating “merchant” vs “developer” (or staging vs production assistant) should be done with separate integrations** that have different **role assignments** and different selected MCP tools:

| Pattern | How it works |
|---------|----------------|
| **Merchant integration** | Narrow ACL plus a small MCP tool selection in Admin; no dangerous entity scopes by default. |
| **Agency / power integration** | Broader ACL plus a broader MCP tool selection, often on staging first. |

Each MCP client configuration (Cursor, Claude, custom app) points at **one** integration. That integration should define both:

- the ACL permissions
- the MCP tools selected for that integration

Authorization still comes from **ACL at `tools/call` time**, not from prompt text or docs hints.

#### Why metadata is not required for V1

For V1, integration + ACL is enough. Metadata is not required for the chosen product flow.

The practical rule is:

- if an integration should not use a tool, do not select that tool in Admin
- if an integration lacks the needed privilege, the call still fails even if the tool is selected

That gives both operator intent and security without introducing a new discovery contract.

#### Can MCP tool selection live on the integration itself?

**Today:** `shopware.mcp.allowed_tools` is a **single global** list at **container compile time** (see `McpToolCompilerPass`). It is **not** stored per API integration.

**V1 product direction:** ship **Admin-configurable MCP tool selection per integration** in **[Epic V1 (pre-SCD)](#epic-v1-before-scd-supported-shop-http-mcp)**. This is the main path to “one Cursor key = one narrowed surface” without YAML deploys.

Extend the **Admin API integration** (access key) with an MCP tool allowlist:

| Capability type | What would be filtered |
|-----------------|-------------------------|
| **Tools** | Tool names (e.g. `shopware-entity-search`, `shopware-order-summary`) visible in `tools/list` and accepted at `tools/call`. |

**Suggested semantics:**

1. **Empty allowlist on a new integration** = **no MCP tools available by default**.
2. **Selected tools** = intersection with registered capabilities **and** still enforce **ACL** on tools.
3. **Evaluation time** = **per HTTP request** on `/api/_mcp`, using the resolved integration id from the authenticated principal.

**Why this is attractive**

- Operators configure **one Cursor connection** = one integration = **one row in Admin** with both **roles** and **MCP surface**.
- Replaces the need for any extra V1 discovery shaping: a merchant integration simply does not **see** `shopware-entity-delete` in `tools/list`.

**Costs / work to expect**

- **Admin UI + API + persistence** on `integration` (or child entity), validation against known names, migrations, defaults when new core tools ship.
- **Runtime filtering** in the MCP server path (after Symfony MCP resolves the registry, or by decorating the registry per request)—must be **fast** and **cached** carefully.
- **Documentation** for partners: how allowlist interacts with **plugins** and **apps** that add new tool names.
- **Tests:** matrix of ACL × allowlist × dry-run.

This is the chosen V1 approach: narrower scope, operator-driven, and aligned with Shopware’s existing integration model.

### Options compared: shaping the MCP surface per caller

This was discussed broadly during exploration. The V1 decision is now fixed:

- **choose per-integration MCP allowlists in Admin**
- **keep ACL as the security gate**
- **defer discovery metadata and other list-shaping mechanisms**

That means:

- no V1 discovery metadata rollout
- no ACL-only discovery shaping project
- no second HTTP MCP surface for developers

---

## MCP spec coverage (2025-11-25 server) vs Shopware

Contributor-oriented summary: [spec-coverage.md](spec-coverage.md)

**Spec index:** [modelcontextprotocol.io/specification/2025-11-25/server](https://modelcontextprotocol.io/specification/2025-11-25/server)

The public docs split **server** capabilities into **Tools**, **Prompts**, **Resources**, and **Utilities** (with sub-pages **Completion**, **Logging**, **Pagination** under Utilities). **Transports, lifecycle, authorization**, and cross-cutting **base protocol** topics live **outside** that `/server` tree but still bind any HTTP server.

**Transport and lifecycle (base protocol, not `/server` subtree):** Shopware uses **Streamable HTTP** at `/api/_mcp` via `symfony/mcp-bundle` and `McpServerController`; session initialization and JSON-RPC routing are **delegated to the bundle / MCP PHP SDK**. Shopware adds **Shopware-only** layers: Admin API auth bridge, rate limits, feature flag, `McpContextProvider`, app HMAC execution, and `McpToolCompilerPass`.

### Server features checklist (from spec navigation)

| Spec topic | Spec link | Shopware / bundle today (high level) | Likely gap or follow-up |
|------------|-----------|----------------------------------------|-------------------------|
| **Server overview** | [server](https://modelcontextprotocol.io/specification/2025-11-25/server) | HTTP MCP enabled; `mcp.yaml` sets app name, description, scan dirs | Document which **capabilities** we advertise in `initialize` (tools / prompts / resources / which utilities); keep in sync when bundle upgrades |
| **Tools** | [server/tools](https://modelcontextprotocol.io/specification/2025-11-25/server/tools) | Many in-process tools + app tools; `#[McpTool]`; tests in `McpCapabilityDiscoveryTest` | No V1 extra discovery metadata work. Keep discovery tests aligned and revisit richer metadata only if later client needs justify it. |
| **Prompts** | [server/prompts](https://modelcontextprotocol.io/specification/2025-11-25/server/prompts) | `shopware-context` + app-backed prompts loader | Optional extra prompts; **arguments / templating** if spec adds stricter shapes; ensure discovery test stays aligned |
| **Resources** | [server/resources](https://modelcontextprotocol.io/specification/2025-11-25/server/resources) | Seven static-style resources + app resources | **Templates / subscriptions** if clients rely on them; **ACL** policy still open (see Workstream 1) |
| **Utilities — Completion** | [server/utilities/completion](https://modelcontextprotocol.io/specification/2025-11-25/server/utilities/completion) | *Unknown without vendor audit* — likely handled or partially handled by **`symfony/mcp-bundle` / SDK** | **Spike:** implement or wire **entity name / field / enum** completions for `shopware-entity-search` criteria if bundle exposes hooks; else contribute upstream |
| **Utilities — Logging** | [server/utilities/logging](https://modelcontextprotocol.io/specification/2025-11-25/server/utilities/logging) | Shopware has an `mcp` Monolog channel, but product telemetry should go through the **telemetry abstraction / OpenTelemetry-capable transport** story | Decide whether to support MCP `logging/setLevel` + `notifications/message` as a real protocol feature, while keeping product metrics on the telemetry side |
| **Utilities — Pagination** | [server/utilities/pagination](https://modelcontextprotocol.io/specification/2025-11-25/server/utilities/pagination) | **Application-level** pagination in tool responses (`_meta`, criteria `page`/`limit`) | Confirm whether **protocol-level** `resources/list` or other MCP list endpoints need spec cursors; align docs |

### Not “server features” on this shop endpoint (still mention in docs)

| Area | Spec link | Note for Shopware |
|------|-----------|---------------------|
| **Client: Roots** | [client/roots](https://modelcontextprotocol.io/specification/2025-11-25/client/roots) | Implemented by **MCP clients**, not Shopware server. Document “N/A on `/api/_mcp`”. |
| **Client: Sampling** | [client/sampling](https://modelcontextprotocol.io/specification/2025-11-25/client/sampling) | Same: **client** asks model; server does not host sampling. |
| **Client: Elicitation** | [client/elicitation](https://modelcontextprotocol.io/specification/2025-11-25/client/elicitation) | Same: interactive **client** UX. |

### What may be **outside** `symfony/mcp-bundle` (Shopware or upstream work)

These are the usual **gaps** when a framework integrates MCP:

1. **Shopware domain completions** (entity names, state machine states, sales channel ids) for the **Completion** utility — often needs **custom handlers**, not generic bundle defaults.  
2. **Strict alignment** of **logging** behavior with the **spec logging** page, while keeping **product metrics** on the telemetry side.
3. **Capability advertisement** in `initialize` vs what we **actually** implement (utilities flags must not lie).  
4. **Version drift:** each **`symfony/mcp-bundle` / `mcp/mcp` SDK** bump should re-run this checklist (vendor tree is not scanned in this doc; run in CI or locally).

### Suggested child issues (epic backlog)

1. **Spike:** read **`symfony/mcp-bundle`** + pinned **`mcp/mcp`** version and fill the **Completion / Logging / Pagination** rows with “supported / stub / not exposed”.  
2. **Document the chosen V1 list-shaping model**: per-integration Admin tool selection, empty by default on new integrations, ACL intersection.
3. **Resources:** decide on **templates + listChanged** vs current static set; align with spec.  
4. **Docs (developer.shopware.com):** one page “**MCP spec coverage**” linking here, [spec-coverage.md](spec-coverage.md), and the official spec revision.
5. **CI:** lightweight test or script that asserts **advertised capabilities ⊆ implemented methods** after bundle upgrades.

---

## Recommended delivery order (dependency aware)

1. **Productize core transport and contracts** (stabilize what exists, remove experimental gate where appropriate) **including [MCP observability](#mcp-observability-day-1-requirement)**, **[per-integration tool selection in Admin](#can-mcp-tool-selection-live-on-the-integration-itself)** ([Epic V1](#epic-v1-before-scd-supported-shop-http-mcp)), and the **[spec vs bundle checklist](#mcp-spec-coverage-2025-11-25-server-vs-shopware)** so every tool call emits structured telemetry for adoption and removal decisions, operators can narrow MCP tools per integration, and advertised MCP capabilities stay honest after upgrades.
2. **Merchant MCP plugin + slim core ([ADR](../../../../../adr/2026-03-17-mcp-server-placement-and-extensibility.md))** — move **workflow-oriented** tools from core into an **installable plugin** (or bundle); keep **primitives** in core ([Workstream 3](#workstream-3-merchant-oriented-capability-plugin-split-from-core)). Run in parallel with **1** once the **inventory** from the ADR is frozen for the first drop.
3. **Public developer documentation** on **developer.shopware.com/docs** — **target pre-SCD**: **extract and polish** from `src/Core/Framework/Mcp/docs/` (bounded first slice; see [Epic V1](#epic-v1-before-scd-supported-shop-http-mcp)). Start in parallel once **1** is stable enough to describe accurately; **reference** [ai-coding-tools](https://github.com/shopwareLabs/ai-coding-tools) and **[Context7 / agent-side doc lookup](#developer-documentation-in-the-agent-loop-no-shop-mcp-tool-in-v1)** instead of inventing shop doc-search tools. **Deep** port belongs in [V2](#epic-v2-after-scd-ecosystem-and-ux-depth).
4. **Official sample App + plugin ([Epic V1](#epic-v1-before-scd-supported-shop-http-mcp))** — **move** [McpHelloWorld](https://github.com/BrocksiNet/McpHelloWorld) and [SwagMcpAdminUsers](https://github.com/BrocksiNet/SwagMcpAdminUsers) under **`shopware` org**, **polish**, update every doc link ([Workstream 5](#workstream-5-example-app-remote-mcp-extension), [Workstream 6](#workstream-6-example-plugin-in-process)).
5. **Client UX follow-ups** — [observability](#mcp-observability-day-1-requirement) is **day 1** (see item **1**). After baseline telemetry exists, revisit whether Admin tool selection is enough or whether later client-facing discovery metadata is worth the extra contract work.
6. **`SwagMcpDevTools` MVP ([Epic V1](#epic-v1-before-scd-supported-shop-http-mcp) / [Workstream 2](#workstream-2-developer-mcp-bundle-remote-reachable-developer-tools))** — installable bundle (Shopware CLI) on SaaS/PaaS/on-prem with **log streaming + search** MVP tools, reusing `/api/_mcp`, integration auth, ACL, rate limiter. Depends on **1** (day-1 observability provides the log/event baseline) and **3** (docs page describing the remote-bundle vs laptop-Labs split).
7. **Developer MCP depth ([Epic V2](#epic-v2-after-scd-ecosystem-and-ux-depth) / [Workstream 2](#workstream-2-developer-mcp-bundle-remote-reachable-developer-tools))** — `SwagMcpDevTools` system/health probes, deprecation + version context, migration status, log ↔ request correlation; optional laptop-side metapackage + Cursor/Claude templates pointing at [ai-coding-tools](https://github.com/shopwareLabs/ai-coding-tools). Depends on V1 MVP and observability schema.

### If the milestone is **beginning of May** (short window)

Assume roughly **one to two weeks** of focused calendar time. **Defer by default** anything that is **XL**, anything that needs a **new discovery contract**, and **greenfield** work such as **extra** sample repositories when [McpHelloWorld](https://github.com/BrocksiNet/McpHelloWorld) / [SwagMcpAdminUsers](https://github.com/BrocksiNet/SwagMcpAdminUsers) already cover the basics. **Do not** treat a **bounded** public docs port as “full migration”: that slice is **[pre-SCD V1](#epic-v1-before-scd-supported-shop-http-mcp)** (extract + polish), not an **exhaustive** site rewrite.

**Reasonable May target:** merge-quality **hardening** of what the POC already does (bugs, CI, changelog, security review follow-ups, in-repo docs touch-ups, flag or release stance), plus a written **“deferred scope”** list for leadership so the roadmap picture is not mistaken for a May checklist.

**Do not promise for May without an explicit cut:** merchant extraction **without** a **frozen ADR tool inventory** and release owners (scope creep = XL trap), **reimplementing** local dev MCP that already exists in `ai-coding-tools`, **`SwagMcpDevTools` depth** beyond the log streaming / search MVP (system probes, correlation — [V2](#epic-v2-after-scd-ecosystem-and-ux-depth)), optional laptop-side **metapackage + editor templates** ([V2](#epic-v2-after-scd-ecosystem-and-ux-depth)), an **exhaustive** MCP documentation program on **developer.shopware.com/docs** (every long appendix, full generated reference) without a **bounded** inventory first, **in-product analytics dashboards / SIEM packs** (baseline **[structured MCP observability](#mcp-observability-day-1-requirement)** is **not** in this defer bucket). **Pre-SCD [V1](#epic-v1-before-scd-supported-shop-http-mcp)** **does** include **[ADR-aligned slim core + merchant plugin](../../../../../adr/2026-03-17-mcp-server-placement-and-extensibility.md)**, the **`SwagMcpDevTools` MVP** (log streaming + search), **`shopware` org move + polish** for the **two** reference samples, and the **bounded** public docs slice (not optional fluff).

---

## Challenging upper-level requirements (drop, defer, shrink)

The six roadmap modules are a **direction**, not a self-evident backlog. This section records **skepticism** you can use in planning: what to **validate with product**, what to **defer** because cost beats value, and what to **drop** because another path already wins.

### Questions to answer before large bets

Ask leadership and PM explicitly (written answers beat slide assumptions):

1. **Who is the customer for `/api/_mcp`?** (SI partner, merchant self-serve, internal Shopware devs, App builders only?) That decides how much “merchant assistant” polish you need versus a **thin platform API**.
2. **Which ADR “move to plugin” tools ship in the first drop vs a fast follow?** Who owns the **merchant MCP plugin** release train and default install policy (core metapackage vs optional in Admin)?
3. **How strict is the V1 allowlist model?** Confirm that new integrations start with **no MCP tools selected**, and that tool selection stays the main list-shaping mechanism.
4. **Will App MCP drive revenue or partner adoption in year one?** If not, deep investment in **example App + prompts + resources** is education, not revenue.
5. **Exact `shopware/*` repo names and releng owners** for the moved samples (coordinate with org admins).

### Strong candidates to **drop** (do not build in Shopware core)

| Upper-level idea | Challenge | Recommendation |
|------------------|-----------|------------------|
| “Dev MCP plugin” = reimplement PHPStan, PHPUnit, ESLint, console as shop tools | **Duplicate** of [ai-coding-tools](https://github.com/shopwareLabs/ai-coding-tools); wrong trust boundary | **Drop** from core. Those stay in Labs. Remote-reachable developer tools live in **`SwagMcpDevTools`** ([Workstream 2](#workstream-2-developer-mcp-bundle-remote-reachable-developer-tools)) and focus on **shop-state** introspection (logs, health), not build/lint. |
| Second HTTP MCP stack for developers on the shop | Confuses operators; expands attack surface | **Drop**. `SwagMcpDevTools` reuses the existing `/api/_mcp` endpoint with existing auth/ACL/rate limits — it is **not** a second stack. |

### Strong candidates to **defer** (value real but not urgent; do after traction)

| Requirement | Why defer | Cheaper substitute for now |
|---------------|-----------|------------------------------|
| **Extra discovery metadata** | Contract + SDK + every extension author must adopt; clients may ignore unknown fields | **Admin tool selection per integration** + ACL + a human-readable tool matrix in docs |
| **In-admin dashboards / SIEM product packs** | Build cost; needs baseline events first | **Defer** until [day-1 structured observability](#mcp-observability-day-1-requirement) ships; then [Phase 2](#phase-2-dashboards-and-siem-packs) |
| **ACL on MCP resources** | Resources are small reference lists today; tool ACL + Admin auth already bound risk | **Document** what resources expose; add ACL only if security review finds sensitive payloads |
| **Load and abuse testing at scale** | Premature without production traffic patterns | Smoke tests + rate limits already in POC; scale testing when adoption appears |
| **Extra** sample repos beyond the two reference implementations | Diminishing returns vs maintenance | **[Epic V1](#epic-v1-before-scd-supported-shop-http-mcp)** already moves + polishes **McpHelloWorld** + **SwagMcpAdminUsers**; add more repos only with owners ([Epic V2](#epic-v2-after-scd-ecosystem-and-ux-depth)) |
| **Rich prompts + resources in the sample App** | Nice for teaching; not required to prove `mcp.xml` + HMAC | Keep **minimal** App; extend when App MCP adoption is measured |
| **Separate merchant vs developer example plugins** | One ACL-aware plugin already demonstrates the pattern | One good doc + one repo beats two half-maintained repos |
| **Full migration / deprecation narrative** for ai-coding-tools | There is nothing to “migrate away” from if core never duplicated those tools | **Companion** one-pager: “HTTP MCP for shop, Labs for local dev” |

### Strong candidates to **shrink** (keep intent, cut scope)

| Requirement | Shrink to | Why it still helps |
|-------------|-----------|---------------------|
| **developer.shopware.com/docs** migration | **Pre-SCD (V1):** ship that **bounded** slice (overview, setup, security, extensibility, examples, cross-links) by **extracting** from `src/Core/Framework/Mcp/docs/` and **polishing** for the web; **post-SCD (V2):** deep pages, exhaustive tool reference, automation | Same as [Epic V1 (pre-SCD docs)](#epic-v1-before-scd-supported-shop-http-mcp): most content already exists; risk is scope creep, not blank-page writing |
| **Merchant bundle extraction** | **[Epic V1](#epic-v1-before-scd-supported-shop-http-mcp):** execute against [ADR inventory](../../../../../adr/2026-03-17-mcp-server-placement-and-extensibility.md); **timebox** any stragglers to a **fast follow** issue rather than blocking GA | ADR already decided placement; remaining risk is **execution + testing**, not whether to do it |
| **Metadata roadmap** | Publish a **human-readable** tool matrix on the docs site **without** changing MCP wire format yet | Same guidance for integrators; protocol change can follow only if still needed |
| **Observability (required)** | [Day-1 structured telemetry](#mcp-observability-day-1-requirement) on every tool call (and prompts/resources when relevant) | Adoption insight and safe removal of unused tools; **no** waiting for “traffic” to justify logs |

### Worth **defending** (high leverage vs effort in the POC line)

These already exist or are close; challenge cuts **elsewhere**, not here:

- **`/api/_mcp` + OAuth / integration auth + rate limits** — the actual product hook.
- **ACL + dry-run on writes** — differentiates Shopware from naive “give the model SQL” patterns.
- **`shopware.mcp.allowed_tools`** — coarse but **immediate** operator control without new protocol fields.
- **App MCP path (`mcp.xml`, HMAC)** — strategic for the ecosystem even if volume starts low.
- **Clear split in docs:** shop HTTP MCP vs [ai-coding-tools](https://github.com/shopwareLabs/ai-coding-tools) local MCP — prevents duplicate engineering.

### One-line summary for steering

**Ship a safe HTTP MCP platform** with [structured MCP observability](#mcp-observability-day-1-requirement) from day one, **[slim core + merchant MCP plugin per ADR](../../../../../adr/2026-03-17-mcp-server-placement-and-extensibility.md)**, **Admin tool selection per integration** with **no tools selected by default** on new integrations, a bounded [public docs slice before SCD](#epic-v1-before-scd-supported-shop-http-mcp) (extract + polish), and **official `shopware/*` samples** (moved + polished). **Reference** [ai-coding-tools](https://github.com/shopwareLabs/ai-coding-tools) and **Context7** for local dev and doc-aware agents; **defer** deep Labs productization, **exhaustive** docs expansion, optional discovery metadata, and **Phase 2 dashboards** to **[V2](#epic-v2-after-scd-ecosystem-and-ux-depth)** unless metrics demand earlier.

---

## Workstream 1: Core MCP platform (extensible host)

**Roadmap module:** P1 Core Extensible MCP Module.

| Item | Status | Notes | Effort | May milestone |
|------|--------|-------|--------|---------------|
| Native host endpoint `/api/_mcp` (streamable HTTP) | **Done (POC)** | `McpServerController`, route `api.mcp.endpoint` | S | **Ship** |
| Registration and discovery for tools, prompts, resources (core + bundles) | **Done (POC)** | MCP SDK + `mcp.yaml` scan dirs, `McpToolCompilerPass`, integration test `McpCapabilityDiscoveryTest` | S | **Ship** |
| App registration (XML, persistence, loaders, HMAC execution) | **Done (POC)** | App aggregates, migrations, `AppMcpToolLoader` / `AppMcpToolExecutor`, XML fixtures in tests | M | **Ship** |
| Security: Admin API auth + integration header auth | **Done (POC)** | `McpAuthenticationListener`, Bearer path unchanged | S | **Ship** |
| ACL on data and outcome tools | **Partial** | Documented in the published MCP configuration and troubleshooting docs; some tools called out as no ACL (e.g. entity-schema); resources explicitly “no ACL” today | M | **Maybe** (tighten only if security review demands; AI helps tests) |
| Rate limiting | **Done (POC)** | `RateLimiter::MCP` in controller; OAuth bucket for integration keys in auth listener | S | **Ship** |
| **[MCP observability](#mcp-observability-day-1-requirement) (structured tool / prompt / resource telemetry)** | **Partial** | `mcp` channel exists, but **product requires** central telemetry via the Shopware telemetry abstraction plus a secondary support log trail — see [MCP observability](#mcp-observability-day-1-requirement). | M | **May** (treat as **GA blocker** unless PM explicitly waives) |
| Per-installation allow-list | **Done (POC)** | `shopware.mcp.allowed_tools` + compiler pass enforcement | S | **Ship** |
| **Per-integration MCP allowlist (Admin UI + API + runtime filter)** | **Open** | Optional allowlisted **tool** names on the **integration**; filter `tools/list` and enforce on `tools/call` **with** ACL ([design](#can-mcp-tool-selection-live-on-the-integration-itself), [Epic V1](#epic-v1-before-scd-supported-shop-http-mcp)). | **M–L** | **Pre-SCD** |
| Naming, conflict detection, error contracts | **Done (POC)** | Compiler pass conflict detection; `McpToolResponse` convention tests | S | **Ship** |
| Generic primitives (entity CRUD-ish, schema, search, aggregate, config) | **Done (POC)** | Entity tools, system config tools | S | **Ship** |
| Write safety (dry-run defaults) | **Done (POC)** | Documented; write tools default `dryRun=true` | S | **Ship** |
| Feature flag `MCP_SERVER` | **Partial** | Good for POC; product needs lifecycle decision (default on, deprecation of flag, compile-time removal strategy) | S | **May** (decision + small code or doc change; AI drafts ADR text) |
| Optional discovery metadata | **Open** | Not emitted today. V1 does not need it; revisit only if Admin tool selection plus docs prove insufficient. | M | **Later** |
| **Phase 2: MCP analytics products** (in-admin dashboards, SIEM packs, anomaly detection) | **Open** | Builds on [day-1 observability](#mcp-observability-day-1-requirement); see [Phase 2](#phase-2-dashboards-and-siem-packs). | L | **Later** |
| ACL for read-only reference resources | **Open** | Today MCP **resources** are documented as reference data without per-field ACL. If product/security wants parity with “least privilege”, define which roles may see which **resource** URIs or payloads (different problem from tool ACL). | M | **Later** |

**Outcome:** Ship a **supported** core that is intentionally **thin**: MCP **foundation** + **DAL-oriented primitives** + app remote execution + clear extension tags. **Merchant assistant** workflows belong in the **plugin** per [ADR](../../../../../adr/2026-03-17-mcp-server-placement-and-extensibility.md) (**[Epic V1](#epic-v1-before-scd-supported-shop-http-mcp)**, [Workstream 3](#workstream-3-merchant-oriented-capability-plugin-split-from-core)).

---

## Workstream 2: Developer MCP bundle (remote-reachable developer tools)

**Roadmap module:** P1 Dev MCP Integration Plugin.

**Tracking Epic:** [#16205](https://github.com/shopware/shopware/issues/16205)

**Working name:** **`SwagMcpDevTools`** (installable Shopware bundle; mirrors `SwagMcpMerchantAssistant` naming).

**Milestone:** **[Epic V1](#epic-v1-before-scd-supported-shop-http-mcp)** for the **MVP** (bundle skeleton + **log streaming / search** tools), since it fills a gap [ai-coding-tools](https://github.com/shopwareLabs/ai-coding-tools) cannot close. **[Epic V2](#epic-v2-after-scd-ecosystem-and-ux-depth)** owns deeper developer tools (system probes, deprecation/version context, log-request correlation) and any optional **local-side** packaging (metapackage, Cursor/Claude editor templates) as a companion to Labs.

### Why `SwagMcpDevTools` is not a wrapper around [ai-coding-tools](https://github.com/shopwareLabs/ai-coding-tools)

Labs's **dev-tooling** plugin is the reference implementation for **laptop-local** developer MCP: three MCP servers (`php-tooling`, `js-admin-tooling`, `js-storefront-tooling`) covering **PHPStan, ECS, PHPUnit, Rector, Symfony console**, plus **ESLint, Stylelint, Prettier, Jest, TS, builds**, with Docker/DDEV/native detection and per-project config. Other Labs plugins add **gh-tooling**, **test-writing**, ChunkHound, and so on. Those servers run **on the developer's machine**, next to the editor, against a **local** Shopware instance.

**They cannot reach a remote Shopware instance** (SaaS, PaaS, staging, on-prem). That remote-reachability gap is what `SwagMcpDevTools` fills: an installable bundle that lives **inside** any Shopware instance, exposing developer-oriented tools through the native HTTP `/api/_mcp` endpoint with the usual integration auth, ACL, and rate limiting. Install flow is a single Shopware CLI command so SaaS/PaaS/on-prem environments all reach the same steady state.

**Complementary, not replacement:** build and lint stay on the laptop (Labs). **Shop-state introspection** (logs, health, feature flags, migrations) runs on the shop (`SwagMcpDevTools`). Both can be configured in the same agent session.

### MVP scope (pre-SCD target, small but useful)

Start with tools that let a developer agent answer the question: **“what's happening on this remote instance right now?”** — without SSH or dashboard hopping.

| Tool | What it does | Why MVP |
|------|--------------|---------|
| **log streaming** | Recent log entries, filter by channel / level / time window, bounded page size, sensitive-field redaction | Unlocks “what broke in the last hour?” and error triage against any remote environment |
| **log search** | Query by message pattern, correlation ID, or route | Lets the agent pivot from an error report to the actual stack context |

All MVP tools are **read-only**. They plug into existing MCP building blocks (integration auth, ACL, rate limiter) — no new transport or auth layer.

### Good vs bad candidates (what belongs in this bundle)

**Good candidates** (require the shop's state, safe as read-only remote tools):

- **log streaming + search** (MVP)
- **system / health probes** — queue depth, scheduled task status, cache state, feature flags
- **version + deprecation context** — framework version, active feature flags, deprecation hits
- **migration status** — applied and pending migrations
- **log ↔ request correlation** — join recent requests / exceptions / log entries for root-cause context

**Bad candidates** (belong on the laptop, stay in Labs):

- PHPStan / PHPUnit / ECS / ESLint / Jest / Prettier / Rector runs
- `bin/console` generic command wrappers
- File-system edits or arbitrary shell
- Any write operation in the MVP

### Relationship to Labs (one-line summary)

| Surface | Owns | Why |
|---------|------|-----|
| **`SwagMcpDevTools`** (this workstream) | Remote-instance introspection via `/api/_mcp` | ai-coding-tools cannot reach remote environments |
| **[ai-coding-tools](https://github.com/shopwareLabs/ai-coding-tools)** | Laptop-local build / lint / test / console | Wrong trust boundary to run on a shop |
| **Public docs** | One clear page explaining the split + when to use which | Prevents duplicate engineering and operator confusion |

### Work items

| Item | Status | Notes | Effort | May milestone |
|------|--------|-------|--------|---------------|
| **`SwagMcpDevTools` bundle skeleton** (CLI install on SaaS/PaaS/on-prem) | **Open** | New bundle; reuses `/api/_mcp`, integration auth, ACL, rate limiter; no new transport | M | **Pre-SCD (MVP)** |
| **Log streaming tool** | **Open** | Filter by channel/level/time; bounded page size; sensitive-field redaction | M | **Pre-SCD (MVP)** |
| **Log search tool** | **Open** | Query by message pattern / correlation ID / route | S–M | **Pre-SCD (MVP)** |
| **Developer-grade ACL + integration role mapping** | **Open** | Only developer-grade integrations see these tools in `tools/list`; enforce on `tools/call` | S | **Pre-SCD (MVP)** |
| **Public docs page** | **Open** | Remote bundle vs local Labs; install, usage, security posture; links from Workstream 4 pages | S | **Pre-SCD (MVP)** |
| System / health probes (queue, scheduled tasks, cache, feature flags) | **Open** | Next after MVP; still read-only | M | **Later (V2)** |
| Deprecation + version context tool | **Open** | Useful for upgrade agents | S–M | **Later (V2)** |
| Migration status tool | **Open** | Read-only; composes with version tool | S | **Later (V2)** |
| Log ↔ request correlation | **Open** | Depends on [day-1 observability](#mcp-observability-day-1-requirement) schema | M | **Later (V2)** |
| PHPStan / PHPUnit / ECS / ESLint / Jest / console runners | **Done (Labs)** | Remain in [ai-coding-tools](https://github.com/shopwareLabs/ai-coding-tools); **do not** rebuild here | — | N/A |
| Optional metapackage + Cursor/Claude templates (for laptop-side onboarding to Labs) | **Open** | Only if measurable demand; does not duplicate Labs servers | S–M | **Later (V2)** |

**Outcome:** Developers debugging a **remote** Shopware instance (SaaS, PaaS, staging, on-prem) get first-class agent support through a **narrow, read-only** bundle (`SwagMcpDevTools`) installable via Shopware CLI, served over the existing `/api/_mcp` endpoint with existing auth, ACL, and rate limits — **no second HTTP MCP stack**. **Local** dev loop stays with [ai-coding-tools](https://github.com/shopwareLabs/ai-coding-tools). MVP is intentionally small (log streaming + search) so V1 ships value without widening the attack surface; deeper probes and correlation land in V2.

---

## Workstream 3: Merchant-oriented capability plugin (split from core)

**Roadmap module:** P2 Merchant-Oriented MCP Capability Plugin (P1 items inside it in the image).

| Item | Status | Notes | Effort | May milestone |
|------|--------|-------|--------|---------------|
| Move opinionated merchant workflows out of core | **Open** | Implement [ADR](../../../../../adr/2026-03-17-mcp-server-placement-and-extensibility.md): workflow tools → **installable plugin** (or bundle); core keeps primitives + MCP foundation. Use **`SwagMcpMerchantAssistant`** as the working plugin name and **`merchant-*`** as the target tool namespace so discovery clearly separates plugin workflows from core `shopware-*` primitives. POC today still has many tools **inside** Core / Storefront | **L–XL** | **Pre-SCD** ([Epic V1](#epic-v1-before-scd-supported-shop-http-mcp); timebox **fast follow** for edge cases) |
| Same extension and security contracts | **Partial** | Tools already use shared patterns; move is mostly packaging + DI + tests + docs | bundled in L–XL | **Pre-SCD** (with merchant plugin drop) |
| Dry-run on write tools | **Done (POC)** | Stays as requirement for merchant tools | S | **Ship** |
| Human-readable tool matrix for core vs merchant plugin | **Partial** | Keep the published tools reference aligned with the ADR extraction and the merchant plugin plan. | S | **Pre-SCD** |

**Outcome:** Core MCP stays **generic** (per [ADR](../../../../../adr/2026-03-17-mcp-server-placement-and-extensibility.md)); merchant “assistant” surface ships as **`SwagMcpMerchantAssistant`** (working name) with a separate **`merchant-*`** tool namespace so shops can disable or scope it independently and clients can distinguish it from core at discovery time.

---

## Workstream 4: Developer documentation (product grade)

**Roadmap module:** P1 Developer Docs for MCP.

### Canonical public docs: [developer.shopware.com/docs](https://developer.shopware.com/docs/)

Today, MCP material lives **in the codebase** as a strong draft set under `src/Core/Framework/Mcp/docs/` (setup, tools, security, extensibility, examples, agent user stories, Cursor rule, and so on). That is useful for contributors and agents working **inside the repo**, but it is **not** the same as **published** documentation.

**Pre-SCD expectation ([Epic V1](#epic-v1-before-scd-supported-shop-http-mcp)):** the **first** public chapter is mostly **extract + polish**, not a blank rewrite. Pick a **bounded** page set (overview, setup, security, extensibility, examples, tool overview or link-out), port from `src/Core/Framework/Mcp/docs/`, then **edit for the web** (tone, IA, screenshots only if needed). **Post-SCD ([Epic V2](#epic-v2-after-scd-ecosystem-and-ux-depth))** covers **depth**: exhaustive reference, automation, and long-tail pages.

**Product requirement:** **Transfer** that content into the **Shopware documentation platform** (the site behind `https://developer.shopware.com/docs/`), then **edit for the web**: information architecture (overview → setup → concepts → guides → reference), consistent tone for external developers, navigation and cross-links (including a **short** pointer to [ai-coding-tools](https://github.com/shopwareLabs/ai-coding-tools), native `/api/_mcp`, and **official** sample repos under **`shopware/*`** after the [V1 move](#epic-v1-before-scd-supported-shop-http-mcp); until then link the **BrocksiNet** mirrors with a “will move” note). See [Developer documentation in the agent loop](#developer-documentation-in-the-agent-loop-no-shop-mcp-tool-in-v1) for **Context7** vs shop doc tools. Keep the in-repo copies **until** the public pages are live, then add **short pointers** in-repo (“canonical docs: …”) so nothing drifts silently.

Typical sub-issues for this workstream:

1. **Inventory** existing `src/Core/Framework/Mcp/docs/*.md` and map each file to a target section on developer.shopware.com.  
2. **Port** content (not a blind copy-paste: split long pages, fix paths, add prerequisites).  
3. **Shape** for discoverability (search keywords, sidebar placement, linking from broader “API” or “extensions” topics).  
4. **Review** with docs + security + PM.  
5. **Redirect or link** from any other stale locations; trim duplicate agent-only noise where the public site needs a cleaner story.  
6. **Link official samples** from the public MCP guides after **[Epic V1](#epic-v1-before-scd-supported-shop-http-mcp) org move** (canonical **`shopware/*`** URLs; today’s sources: [SwagMcpAdminUsers](https://github.com/BrocksiNet/SwagMcpAdminUsers), [McpHelloWorld](https://github.com/BrocksiNet/McpHelloWorld)). Support level = **official** once under `shopware` with maintenance owners.

| Item | Status | Notes | Effort | May milestone |
|------|--------|-------|--------|---------------|
| Extension guide (plugins, bundles, apps) | **Partial** | Source: official MCP extension guides plus remaining repo-internal notes; **destination:** developer.shopware.com | M (port + IA, not only copy-edit) | **Pre-SCD** (bounded chapter) / **Later** (exhaustive guide) |
| **Publish MCP documentation on developer.shopware.com/docs** | **Open** | **Pre-SCD:** bounded extract + polish from `src/Core/Framework/Mcp/docs/` (see [Epic V1](#epic-v1-before-scd-supported-shop-http-mcp)). **Post-SCD:** full site chapter, automation, long-tail ([Epic V2](#epic-v2-after-scd-ecosystem-and-ux-depth)) | **M–L** (first slice parallelizable) | **Pre-SCD** first slice; **Later** for full parity |
| Architecture: core vs dev plugin vs external | **Partial** | Source: `agent-user-stories.md` + ADR-style narrative; **destination:** public “Concepts” or overview | S–M | **Pre-SCD** (in first slice) / **Later** (polish) |
| Security (HTTP MCP + integration + allowlist + dry-run) | **Partial** | Source: official MCP configuration and troubleshooting docs; local-tool expectations tie to Workstream 2 | M | **Pre-SCD** |
| Migration / companion story for `ai-coding-tools` | **Partial** | Public page: native `/api/_mcp` vs local Labs MCP | S–M | **Pre-SCD** |
| Editor-specific best practices | **Partial** | Source: official troubleshooting guidance for Cursor and related client docs | S | **Later** |
| In-repo docs maintenance after go-live | **Open** | Short index pointing to canonical URLs; avoid two sources of truth long term | S | **After** first public slice ships |
| **Worked examples (plugin + app)** | **Partial** | Public docs: clone, install, prerequisites, relation to `/api/_mcp`; URLs must track **[Epic V1](#epic-v1-before-scd-supported-shop-http-mcp)** **`shopware/*`** move + polish | M | **Pre-SCD** |

---

## Workstream 5: Example App (remote MCP extension)

**Roadmap module:** P2 Example App MCP Extension.

**Existing sample:** [BrocksiNet/McpHelloWorld](https://github.com/BrocksiNet/McpHelloWorld) — **pre-SCD [Epic V1](#epic-v1-before-scd-supported-shop-http-mcp):** **move** to **`shopware` org**, **polish** (README, compatibility, light CI), then **link from developer.shopware.com**. After the move, treat BrocksiNet URLs as **legacy redirects** only if GitHub supports them. **Post-SCD:** richer prompts/resources ([Epic V2 samples row](#epic-v2-after-scd-ecosystem-and-ux-depth)).

| Item | Status | Notes | Effort | May milestone |
|------|--------|-------|--------|---------------|
| `Resources/mcp.xml` registration | **Done (POC)** | Parsing, persistence, tests | S | **Ship** |
| Signed webhook / HMAC execution | **Done (POC)** | `AppMcpToolExecutor` | S | **Ship** |
| Published minimal reference app (GitHub) | **Partial** | **Exists:** [McpHelloWorld](https://github.com/BrocksiNet/McpHelloWorld). **[Epic V1](#epic-v1-before-scd-supported-shop-http-mcp):** **`shopware` org** + polish + canonical docs links | S–M | **Pre-SCD** |
| Non-trivial example tool | **Open** | Improve **McpHelloWorld** or add a second example if the sample is too thin for partners | S–M | **Later** |
| Prompts + resources in example | **Open** | Add to **McpHelloWorld** (or successor) if not already covered | M | **Later** |
| Merchant vs developer classification demo | **Open** | Depends on metadata epic | S | **Later** |

---

## Workstream 6: Example Plugin (in-process)

**Roadmap module:** P2 Example Plugin MCP Extension.

**Existing sample:** [BrocksiNet/SwagMcpAdminUsers](https://github.com/BrocksiNet/SwagMcpAdminUsers) — **pre-SCD [Epic V1](#epic-v1-before-scd-supported-shop-http-mcp):** **move** to **`shopware` org**, **polish**, align with `shopware.mcp.tool` / `#[McpTool]` conventions, update the published extension guides to the **canonical `shopware/*` URL**. **Post-SCD:** second example or template extraction if still needed ([Epic V2](#epic-v2-after-scd-ecosystem-and-ux-depth)).

| Item | Status | Notes | Effort | May milestone |
|------|--------|-------|--------|---------------|
| In-process registration doc | **Partial** | Published plugin extension guide + repo after **`shopware/*`** move | S | **Pre-SCD** |
| ACL-aware example | **Partial** | **Implemented** in **SwagMcpAdminUsers**; polish + public how-to as part of **V1** docs slice | S–M | **Pre-SCD** |
| Unified discovery with core + app | **Done (POC)** | Covered by discovery test and architecture | S | **Ship** |
| Separate merchant vs developer examples | **Open** | Could be a **second** small plugin or a chapter comparing SwagMcpAdminUsers to merchant-only bundles | M | **Later** |
| Template plugin / fixtures for teams | **Partial** | **SwagMcpAdminUsers** is the practical template; optional: extract **cookiecutter** / ZIP template or move under Labs | M | **Later** |

---

## Cross-cutting: Observability and operations

| Item | Status | Effort | May milestone |
|------|--------|--------|---------------|
| **[MCP observability](#mcp-observability-day-1-requirement)** (structured usage telemetry) | **Partial** | M | **May** (align with Workstream 1; **GA** expectation) |
| CI jobs and release notes for MCP flag | **Partial** | M | **May** |
| Load and abuse testing on `/api/_mcp` | **Open** | M | **Later** |
| Support playbook (integration permissions, allowlists) | **Open** | S | **Maybe** (AI drafts checklist; SME review) |
| **Quarterly “tool census”** from telemetry | **Open** | S | **Later** (process: PM + engineering review zero/low-use tools for removal per ADR) |

---

## Quick gap list (POC vs roadmap image)

**Already strong in POC**

- `/api/_mcp` endpoint and Streamable HTTP.
- Discovery across Core, Storefront bundle tools, and plugin attribute path.
- Apps: XML, DB, signed remote tools.
- ACL + dry-run + allowlist + rate limit + conflict detection + internal documentation set.
- **`mcp` log channel** (useful support trail, but not enough on its own for **[day-1 structured observability](#mcp-observability-day-1-requirement)**).

**Largest structural gap**

- **Developer-facing MCP:** **laptop-local** tooling stays in [ai-coding-tools](https://github.com/shopwareLabs/ai-coding-tools); **remote-instance** developer tooling ships as **`SwagMcpDevTools`** ([Workstream 2](#workstream-2-developer-mcp-bundle-remote-reachable-developer-tools)) — **[Epic V1](#epic-v1-before-scd-supported-shop-http-mcp) MVP** is log streaming + search on `/api/_mcp`; **[Epic V2](#epic-v2-after-scd-ecosystem-and-ux-depth)** adds system/health probes, deprecation context, correlation, and optional laptop-side packaging (metapackage + editor templates as a Labs companion). **Not** reimplementing PHPStan or console inside Shopware core.
- **Merchant workflows:** **[Epic V1](#epic-v1-before-scd-supported-shop-http-mcp)** moves them **out of core** into a **plugin** per [ADR](../../../../../adr/2026-03-17-mcp-server-placement-and-extensibility.md); gap is **execution**, not direction.

**Medium gaps** (mostly **Later** for a beginning-of-May milestone)

- Optional discovery metadata in protocol responses for clients, only if Admin tool selection and docs prove insufficient.
- **Day-1 gap:** [structured MCP observability](#mcp-observability-day-1-requirement) beyond ad-hoc debug lines — required to prove which tools deserve core vs bundle placement.
- **Public** doc set on **https://developer.shopware.com/docs/** (migrate + reshape from `src/Core/Framework/Mcp/docs/`) and **ai-coding-tools** companion story.
- **Reference repos:** minimal App ([McpHelloWorld](https://github.com/BrocksiNet/McpHelloWorld)) and plugin ([SwagMcpAdminUsers](https://github.com/BrocksiNet/SwagMcpAdminUsers)) **exist**; **[Epic V1](#epic-v1-before-scd-supported-shop-http-mcp)** closes the gap with **`shopware` org**, **polish**, and **canonical docs** (not greenfield code).

**Smaller gaps**

- Optional **extra** local MCP conveniences (log tailing, and so on) in **Labs** or docs, not duplicate `php-tooling` in core.
- Optional ACL on resources if product security review demands it.

### Explicit **defer past May** list (proposal)

Use this as a **child issue** list under a single GitHub Epic (“MCP post-May”) so the May milestone stays honest.

- **Straggler tools** or **second-pass** extractions not completed in the **first ADR-aligned** merchant plugin drop (track as follow-ups, not a second “big bang”).
- **Reimplementing** local dev MCP (PHPStan, PHPUnit, ECS, JS, console) inside Shopware: **not needed**; those stay in [ai-coding-tools](https://github.com/shopwareLabs/ai-coding-tools). **Not deferred:** `SwagMcpDevTools` **MVP** (log streaming + search) is in **V1**; deeper `SwagMcpDevTools` tools (system/health probes, deprecation context, correlation) and optional **laptop-side** metapackage + editor templates for Labs onboarding are **V2**.
- Optional discovery metadata in MCP discovery, only if later product evidence justifies the extra contract.
- **In-product MCP analytics dashboards** and packaged SIEM **only** if not counting the [day-1 structured log baseline](#mcp-observability-day-1-requirement) (that baseline is **not** deferred here).
- **ACL** on read-only resources (if pursued).
- **`shopware://state-machine/{name}` resource template** — replace the current single-blob `shopware://state-machines` resource with a URI template so clients can fetch individual state machines (e.g. `shopware://state-machine/order.state`) without receiving the full dump. Requires `addResourceTemplate` registration, a `WHERE technical_name = :name` query filter, and keeping `shopware://state-machines` as a lightweight name-list fallback for clients that don't support template expansion. Low urgency: the full-dump resource works correctly today; the template improves targeting for clients that browse resources.
- **Full** MCP documentation set on **https://developer.shopware.com/docs/** (beyond a minimal landing page, if that is all May allows).
- **Heavy extensions** to the two samples (extra tools, prompts, second repos) past May unless prioritized—**org move + polish** for those two is **[pre-SCD V1](#epic-v1-before-scd-supported-shop-http-mcp)**, not deferred.
- **Load / abuse** testing campaign.
- Full **migration** narrative and deprecation timeline for `ai-coding-tools` (short stub in-repo can still land in May).

---

## Suggested parent issues (epics) and what becomes sub-work

Below is one sane **flattening**: **3 parent epics**, with the numbered **workstreams** and **table rows** mapped to **stories / sub-issues** (not seven separate epics).

### Parent A — MCP platform (core product)

Sub-issues roughly from **Workstream 1** (rows: endpoint, discovery, apps, auth, ACL gaps, rate limit, **[day-1 MCP observability](#mcp-observability-day-1-requirement)**, global + **per-integration** allowlists, naming, **core** primitives after [ADR slim-down](../../../../../adr/2026-03-17-mcp-server-placement-and-extensibility.md), dry-run, flag lifecycle, resource ACL, optional later discovery metadata, **[Phase 2 analytics](#phase-2-dashboards-and-siem-packs)**). **Merchant tool moves** are tracked under **Parent B / Workstream 3** if you split epics that way.

### Parent B — MCP extensions (merchant plugin + samples + V2 Labs depth)

Sub-issues from **Workstream 3** (**[Epic V1](#epic-v1-before-scd-supported-shop-http-mcp)** merchant MCP plugin + slim core per [ADR](../../../../../adr/2026-03-17-mcp-server-placement-and-extensibility.md)), **Workstreams 5–6** (**`shopware` org move + polish** for samples), and **Workstream 2** (**V1 MVP:** `SwagMcpDevTools` bundle with log streaming + search; **V2:** system/health probes, deprecation/version context, correlation, optional laptop-side metapackage + editor templates as a Labs companion — **not** duplicate MCP servers).

### Parent C — MCP documentation and enablement

Sub-issues from **Workstream 4** (migrate and reshape content for **https://developer.shopware.com/docs/**, architecture narrative, companion story for ai-coding-tools, security chapters, in-repo pointers after go-live).

**Optional fourth parent** if you split references: **“MCP reference implementations”** ( **`shopware` org** move + polish for the two samples; child issues under **[Epic V1](#epic-v1-before-scd-supported-shop-http-mcp)**).

### Extra titles if you prefer more granular parents

These are **alternative parent names** for the same bodies of work, not an extra layer of “every line = one epic”:

- **MCP core GA hardening** (subset of Parent A).
- **Optional MCP discovery metadata** (later only, if needed; subset of Parent A).
- **Merchant MCP plugin (ADR extraction)** (main chunk of Parent B for **[Epic V1](#epic-v1-before-scd-supported-shop-http-mcp)**).
- **`SwagMcpDevTools` MVP (V1)** (log streaming + search on `/api/_mcp` via a CLI-installable bundle; subset of Parent B; fills the remote-instance gap Labs cannot cover).
- **MCP developer enablement (V2)** (`SwagMcpDevTools` depth — system/health probes, deprecation/version context, correlation — plus optional laptop-side metapackage + Cursor/Claude templates for Labs onboarding; subset of Parent B after the V1 MVP ships; **not** a second `php-tooling`).

---

*Generated from a read-through of `poc/mcp-bundle` style implementation in this repository. Effort tags are informal; align parent Epics with `delivery-process/issues-and-labels.md` (1–3 month Epics, child issues, `domain/` + `priority/`). Adjust after team sizing and security review.*

*Related in-repo docs: [README.md](README.md) (internal index), [spec-coverage.md](spec-coverage.md) (protocol audit), [agent-user-stories.md](agent-user-stories.md) (merchant scope vs ai-coding-tools).*
