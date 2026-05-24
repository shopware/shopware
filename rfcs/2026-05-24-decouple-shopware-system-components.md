# RFC: Decouple Shopware — System Components

Author: @sSayakci · Date: 2026-05-24

The current shape of `shopware/platform` puts everything on one release train. Domains that move much faster than the platform — AI / MCP, the Extension Kit, document generation, analytics, webhook delivery — are bottlenecked behind quarterly minors. Domains that move much slower — DAL, Kernel, Cart, Pricing, Tax — carry the support and BC weight for everything else.

ADR [`2026-03-17-mcp-server-placement-and-extensibility`](../adr/2026-03-17-mcp-server-placement-and-extensibility.md) already split MCP into a stable platform foundation (in core) and fast-moving capability tools (in plugin/bundle). ADR [`2021-08-11-make-platform-stand-alone`](../adr/2021-08-11-make-platform-stand-alone.md) made the monorepo self-contained. This RFC generalises the MCP split into a project-wide architectural pattern: **System Components** — independently-versioned Symfony bundles that ship as their own Composer packages on top of a stable kernel.

Mental model: Android Components updated via Play Store, but bound by what Composer can actually express. Components iterate freely between core minors; they do not replace the App system or Plugin system.

Issues this RFC addresses:

- AI / MCP iteration is gated by core's release cadence; the LLM and MCP spec ecosystem moves on weeks, not quarters.
- The Extension Kit (In-App Purchases, Meteor SDK bridge, partner Store integration, App scaffolding) is the foundation of partner velocity, yet today it ships only with core minors.
- New greenfield domains (Webhook Outbox per ADR 2026-04-14, Document Generation per the three March 2026 ADRs) have no documented home other than `Core/`.
- The core surface grows monotonically — every feature added is a new BC obligation for the platform team.
- Plugin authors have no clean way to depend on "AI Kit ≥ 2.1" independently of "Shopware core ≥ 6.8".

## Decisions

### 1. Introduce a two-layer architecture: Kernel and System Components

The platform is partitioned into two layers with different stability and cadence guarantees.

**Kernel** — single release train, shipped as `shopware/core`, today's cadence:

- Framework, DAL, Kernel, Plugin / App system
- Cart, Pricing, Rule engine, Tax
- Order, Customer, Product domain entities and their schemas
- Authentication, ACL, API layer (Admin / Store / Sync)
- Storefront and Administration hosts (these *contain* components; they are not turned into components in this RFC)

**System Components** — independent release trains, one Composer package per component:

- Each component is a `Shopware\Core\Framework\Bundle` subclass
- Each component declares its own SemVer line
- Each component declares a Shopware core compatibility range (`"shopware/core": "^6.8 || ^6.9"`)
- Each component owns its CHANGELOG, UPGRADE notes, and deprecation policy

**Rationale.** The two-layer split mirrors what the MCP ADR already did de-facto and what Android Components, Symfony Flex recipes, and the JetBrains platform plugins all converged on: a slow, stable kernel and a fast, capability layer on top. It lets the platform team shrink its BC obligations without shrinking Shopware's feature set, and it lets component teams ship at the speed their domain demands.

**Considered alternative: one-package-per-feature with shared SemVer.** Rejected — solves the surface-area problem but not the cadence problem. AI Kit would still be gated by core's minor.

**Considered alternative: distribute components as Shopware Store apps.** Rejected as primary distribution model — apps cannot ship Doctrine migrations, compiler passes, or DI bundles. Apps remain the *fourth* extension axis (after kernel, components, and plugins), not a substitute for components.

**Trade-off.** "Independent cadence" has a ceiling: a component that owns migrations is still bound to the core minor that introduced any referenced kernel column. The promise is *independent between core minors, not between core majors*.

### 2. Distribute System Components as independently-versioned Composer packages

Each component is a separate Composer package under the `shopware/*` namespace, with its own version line.

```json
// shopware/ai-kit composer.json
{
  "name": "shopware/ai-kit",
  "type": "shopware-platform-plugin",
  "require": {
    "php": "~8.2.0 || ~8.3.0 || ~8.4.0 || ~8.5.0",
    "shopware/core": "^6.8 || ^6.9"
  },
  "extra": {
    "shopware-plugin-class": "Shopware\\AiKit\\AiKit",
    "shopware-component": {
      "tier": "system",
      "support": "lts"
    }
  }
}
```

```text
release train illustration

core 6.8.0 ──┬── ai-kit 1.0   extension-kit 1.0   webhook-outbox 1.0
             ├── ai-kit 1.1   (new MCP spec rev)
             ├── ai-kit 1.2   (new model provider)
             ├── extension-kit 1.1   (IAP partner API change)
core 6.8.1 ──┤
core 6.9.0 ──┴── ai-kit 2.0   (new core range, breaking component API)
```

The `shopware/production` template pins a tested matrix. Operators run `composer update shopware/ai-kit` between core releases to pick up component fixes without a full platform upgrade.

**Rationale.** Composer is the existing distribution channel, the existing dependency-resolution mechanism, and the existing security-advisory channel (FriendsOfPHP). Reusing it is the cheapest path to "independent cadence with bounded compatibility".

**Considered alternative: monolithic platform with feature flags.** Rejected — feature flags hide instability but do not let an operator pick up a security fix in AI Kit without taking everything else in the platform release.

**Considered alternative: a Shopware-native component registry.** Rejected for now — Composer already does this. Revisit only if Composer constraints prove insufficient.

**Trade-off.** A compatibility matrix (core × component versions) must be maintained and tested. This is real cost; mitigation in Decision 6.

### 3. Define a System Component contract

Every System Component must:

1. Extend `Shopware\Core\Framework\Bundle`.
2. Depend only on the kernel and other declared System Components — never reach into Storefront or Administration internals, and never `use` symbols marked `@internal` in other components.
3. Mark its public PHP API with `@api` annotations and the `#[Package]` attribute; everything else is `@internal`.
4. Ship its own Doctrine migrations under its own namespace. **Components may not modify kernel tables**; they may only create, alter, and drop tables they own.
5. Use only stable kernel extension points: events, DI tags, decoration, App scripting, MCP capability registration.
6. Carry its own `CHANGELOG.md` and `UPGRADE-X.Y.md`. Deprecations follow a published policy *independent* of core's deprecation policy.
7. Ship its own admin module (if any) using the same Vue 3 / Meteor SDK conventions as core.

```php
namespace Shopware\AiKit;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;

#[Package('ai-kit')]
class AiKit extends Bundle
{
    public function getMigrationNamespace(): string
    {
        return 'Shopware\\AiKit\\Migration';
    }
}
```

**Rationale.** Without an enforceable contract, "System Component" is just rebranding. The contract is what makes the compatibility matrix bounded and what lets the core team say "yes" to extracting a component without taking on more BC weight.

**Considered alternative: enforce the contract by convention only.** Rejected — Shopware's history shows that convention-only boundaries leak. The contract must be CI-enforced (Decision 6).

### 4. Criteria — what becomes a System Component, what stays in the kernel

A domain becomes a System Component when **two or more** of these hold:

- Release cadence demand exceeds one minor per quarter
- Upstream APIs or specs churn frequently (LLM providers, MCP spec, payment gateways, file formats)
- It is an opinionated workflow layer, not a primitive
- It is optional for a working shop (Shopware must run without it)
- It has a self-contained domain boundary (own entities, own admin module)
- It has strong partner / ecosystem dependency (Meteor, Store, Extension Kit)

A domain stays in the kernel when **any** of these hold:

- Other components transitively depend on its schema or runtime
- It is a commerce primitive (cart, pricing, tax, rule, order, customer, product)
- Removing it breaks the install
- It is the security or API surface (Auth, ACL, Admin / Store / Sync API)

**Rationale.** Without explicit criteria, every team will argue that *their* domain qualifies. Explicit criteria turn the question into a checklist, which scales better than case-by-case argument.

**Considered alternative: list components only, no criteria.** Rejected — the list will grow; the criteria are what governs the growth.

### 5. Initial component list and phased extraction

| Component | Source today | Why now | Phase |
|---|---|---|---|
| `shopware/webhook-outbox` | New per ADR 2026-04-14 | Greenfield — starts as a component, never enters core | 1 |
| `shopware/ai-kit` | `Core/Framework/Mcp` workflow tools + LLM glue (foundation stays in core per MCP ADR) | LLM / MCP spec churn; MCP ADR already lit the path | 1 |
| `shopware/extension-kit` | `Core/Framework/Store`, `Core/Framework/Store/InAppPurchase`, App-system extension points, Meteor SDK glue | Partner ecosystem velocity; the base for new extension features | 1 |
| `shopware/document-generation` | `Core/Checkout/DocumentV2` | Three active ADRs in March 2026 indicate ongoing churn | 2 |
| `shopware/commerce-intelligence` | Dashboard widgets, revenue / bestseller reports, MCP reporting tools | Opinionated, merchant-feedback-driven | 2 |
| `shopware/flow-actions` | `Core/Content/Flow/Dispatching/Action` | Actions evolve, executor is stable | 3 |
| `shopware/notifications` | `Administration/Notification` | Self-contained, frequently customised | 3 |
| `shopware/b2b-kit` | Today partially separate | Already a distinct product surface | 3 |

Phase 1 ships with the next minor as a pilot. Phase 2 begins once Phase 1 contracts are observed stable for at least one minor. Phase 3 follows from Phase 2 learnings.

**Rationale.** Phasing protects the kernel from a big-bang extraction and gives the platform team observable signals about whether the contract holds before extracting domains that have more BC weight.

**Considered alternative: extract everything at once.** Rejected — too much coordination risk, no time to learn from the first cohort.

**Considered alternative: one pilot only (AI Kit), generalise later.** Rejected — one component does not stress the contract enough. Three (Webhook Outbox greenfield, AI Kit hot-spot, Extension Kit ecosystem-shaped) cover the three dimensions of risk.

### 6. Governance — compatibility matrix and CI enforcement

The platform CI grows three new responsibilities:

1. **Matrix test.** For each supported (core × component) version pair declared in the tested matrix, run the component's integration suite against the corresponding core. The matrix lives in `shopware/production` and in each component's `composer.json` constraint.
2. **Public-API guard.** A BC-checker (e.g. Roave/BackwardCompatibilityCheck) runs per component on every PR. Breaking changes outside a MAJOR bump fail the build.
3. **Boundary guard.** A static analysis rule (PHPStan custom rule, or ArchUnit-style) asserts that a component imports only kernel symbols and symbols from declared component dependencies. Components must not use `@internal` symbols from other components.

```yaml
# illustrative matrix in shopware/production
shopware-component-matrix:
  core: 6.8.x
  components:
    ai-kit: ">=1.2 <2.0"
    extension-kit: ">=1.1 <2.0"
    webhook-outbox: ">=1.0 <2.0"
```

**Rationale.** A contract that isn't enforced is a wish. Without CI gates, "independent cadence" degrades into "diverging codebases".

**Considered alternative: trust component teams to honour the contract.** Rejected — see history of cross-bundle imports in `Core/` and `Storefront/`.

### 7. Monorepo development, split-package release

`shopware/platform` remains the development monorepo. Components live under `src/Components/<Name>/` (or are admitted to the existing `src/` tree if they extract from there) and are split-released to standalone `shopware/<component>` packages by an existing mechanism analogous to today's `replace:` map for `shopware/core`, `shopware/storefront`, `shopware/administration`.

```
shopware/platform (dev monorepo)
└── src/
    ├── Core/                       # kernel
    ├── Administration/             # host
    ├── Storefront/                 # host
    ├── Elasticsearch/              # existing bundle (kept as-is for now)
    └── Components/
        ├── AiKit/                  # → shopware/ai-kit
        ├── ExtensionKit/           # → shopware/extension-kit
        └── WebhookOutbox/          # → shopware/webhook-outbox
```

**Rationale.** Monorepo development is what makes cross-cutting refactors tractable and what makes the matrix tests cheap to run. Split releases are what give operators the independent cadence promise. The pattern is already in production for `shopware/core` and friends; this RFC extends it.

**Considered alternative: a separate GitHub repo per component.** Rejected for now — multi-repo development is the failure mode ADR 2021-08-11 explicitly fixed for `shopware/development`. Revisit only if a component grows a team whose cadence is *fully* divorced from platform.

**Trade-off.** Contributors must understand both the monorepo and the published-package perspectives. Mitigated by documentation in `developer.shopware.com`.

## Confirmed existing patterns

### 1. Plugin and App systems remain unchanged

System Components are a new layer *between* the kernel and the existing extension axes. Plugins and Apps continue to extend the platform as today, and they may depend on a specific component version range exactly as they depend on a specific core version range.

### 2. The Bundle base class stays the integration point

Components extend `Shopware\Core\Framework\Bundle`. No new framework abstraction is introduced. The Bundle base already provides migration registration, filesystem registration, and DI loading — those are exactly the hooks a component needs.

### 3. The ADR process continues for per-component decisions

This RFC defines the framework. Each component extraction gets its own ADR (placement, public API, deprecation policy, owning team). The MCP placement ADR is the working example of that follow-up shape.

### 4. Storefront and Administration are not turned into components in this RFC

They are too central, too coupled to the host, and too large to extract in this round. A future RFC may revisit; this one does not.

## Consequences

### For the Core team

- Smaller stable surface — every component extracted is BC weight removed from core.
- New responsibility: maintain the component contract (Decision 3) and the boundary / matrix CI (Decision 6).
- New responsibility: review component-extraction proposals against the criteria (Decision 4).
- Security advisories now distinguish "core advisory" from "component advisory" — clearer customer communication.

### For Component teams

- Own release cadence between core minors.
- Own CHANGELOG, UPGRADE, and deprecation policy — at the cost of writing and maintaining them.
- Each team owns its compatibility matrix declaration and is on the hook when CI fails.
- Component versions must be visible in the admin support panel (so support can ask "which AI Kit version?").

### For Plugin and App developers

- New extension target: plugins may depend on a specific component version range (e.g. `"shopware/ai-kit": "^1.2"`).
- Public-API guarantees per component — symbols marked `@api` are stable across the component's MAJOR.
- Apps gain new capability surfaces faster (AI tools, IAP) without waiting for a core minor.
- Cost: plugin authors must understand which component owns which extension point. Mitigation: a `/components` overview page in `developer.shopware.com` and clearer `composer why` guidance.

### For Operators and Merchants

- Can pick up an AI Kit fix between core minors without a full platform upgrade.
- Per-component version pin in `composer.json` — and per-component visibility in the admin.
- `shopware/production` template ships a tested matrix; advanced operators may diverge from it at their own risk.

### For Documentation

- New per-component UPGRADE files (aggregated on `developer.shopware.com`).
- A `/components` catalogue page with status, version, owning team, and compatibility matrix.
- The "where does X live" question gets a single canonical answer per concern.

### Migration

- **Phase 1 (next minor):** extract `shopware/webhook-outbox` (greenfield, no migration), `shopware/ai-kit` (move MCP workflow tools per the MCP ADR), `shopware/extension-kit` (move `Core/Framework/Store` and `InAppPurchase`). Ship CI matrix + boundary guard.
- **Phase 2 (minor +1):** `shopware/document-generation`, `shopware/commerce-intelligence` — pending lessons from Phase 1.
- **Phase 3:** the long tail (`flow-actions`, `notifications`, `b2b-kit`).
- For each extraction: an ADR documents placement and public API; a deprecation shim in core re-exports the moved symbols for one minor so plugins are not broken on the day of the extraction.

## Trade-offs

- **Independent cadence is bounded by Composer.** A component cannot meaningfully ship a new entity that depends on a kernel column that does not yet exist in any supported core minor. The promise is "fast iteration on top of a stable kernel", not "fully decoupled".
- **The compatibility matrix is real cost.** It must be tested, documented, and communicated. The alternative — no matrix — is worse because incompatibility surfaces at the operator's `composer update`, not in CI.
- **Component boundaries are political, not only technical.** Each component needs an owning team. Naming owners is part of every per-component ADR.
- **Storefront and Administration remain monolithic in this round.** Componentising them is a much larger conversation and is explicitly out of scope here.

## Open questions

1. Do components live under `src/Components/<Name>/` or at `src/<Name>/` (matching `Storefront`, `Elasticsearch`)? Naming impacts upgrade scripts and autoloading.
2. Each component's GitHub repository: split out, or stay in the monorepo with split-package release? This RFC defaults to the latter; revisit per component if a team's cadence diverges fully.
3. Support window per component — same as core LTS, or independent? Suggested default: same as core LTS to keep operator messaging simple; advanced components may opt out with justification.
4. Cross-component dependencies — allowed at all, or only through kernel-mediated events? Suggested default: allowed but must be declared in `composer.json` and tested in the matrix.
5. Component naming — `shopware/ai-kit` vs `shopware/ai` vs `shopware/component-ai`? Pick a convention before Phase 1 ships.
