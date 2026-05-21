---
title: UCP feature flag and bundle placement
date: 2026-05-20
area: framework
tags: [framework, ucp, agentic-commerce, store-api, mcp, feature-flag, bundle]
---

## Context

Shopware introduces support for the **Universal Commerce Protocol (UCP)** — an open
standard that enables agentic commerce platforms (ChatGPT, Perplexity, Gemini,
Claude, custom AI agents) to autonomously discover, browse, cart, and check out
at any UCP-compliant business through a uniform interface.

UCP defines a layered architecture:

- **Protocol-Discovery** via a profile at `/.well-known/ucp`
- **Capabilities** such as Catalog, Cart, Checkout, Order, Identity Linking
- **Extensions** that augment capabilities (Discount, Fulfillment, Buyer Consent,
  Loyalty, AP2 Mandates)
- **Transports** — REST (baseline), MCP, A2A, Embedded
- **Cryptographic identity** via RFC 9421 HTTP Message Signatures

This raises three placement questions for the initial Shopware integration:

1. **Where in the source tree should the UCP foundation live?**
   - Within an existing area (`Core/Checkout`, `Core/Framework/Mcp`)
   - As a sibling of the MCP foundation under `Core/Framework/Ucp`
   - As a new top-level `Core/Commerce` area
2. **How should it be gated during inkubation?**
   - Class-level `@experimental` annotation only
   - A feature flag analogous to `MCP_SERVER`
   - A combination
3. **How do we keep capabilities pluggable so that plugins and apps can extend the
   capability surface without core changes?**

UCP shares conceptual structure with the existing MCP server (capability registry,
discovery, attribute-based registration), so the placement decision should be
consistent with `2026-03-17-mcp-server-placement-and-extensibility.md`.

## Decision

### Bundle placement

We place the UCP foundation at **`src/Core/Framework/Ucp/`**, sibling to the
existing `src/Core/Framework/Mcp/`. Capability-specific adapters that wrap a
domain primitive remain co-located inside `Ucp/Capability/{Domain}/` to keep
the dependency direction one-way (Ucp -> Core domains, never the other way).

We do **not** create a new top-level `Core/Commerce/` area for this initial
integration. UCP is a protocol foundation comparable to MCP, not a new
commerce domain.

### Feature flag

We introduce **`UCP_SERVER`** as a runtime feature flag with the same semantics
as `MCP_SERVER`:

- Registered in `src/Core/Framework/Resources/config/packages/shopware.yaml`
- Default value: **`false`** while the foundation is experimental
- All Ucp DI services are tagged `shopware.feature: { flag: UCP_SERVER }` and
  removed from the container by the `FeatureFlagCompilerPass` when disabled
- All routes are gated; a disabled flag returns `404` for `/.well-known/ucp`,
  `/ucp/v1/*`, `/ucp/mcp`, and `/ucp/oauth/*`
- All Admin API endpoints under `/api/_admin/ucp/*` are gated identically
- The Admin UI module `sw-settings-ucp` is hidden behind
  `Shopware.Feature.isActive('UCP_SERVER')`

### Extensibility model

The UCP foundation exposes three extension surfaces:

1. **Capability registration** via the `#[UcpCapability]` PHP attribute combined
   with the DI tag `ucp.capability`. The `UcpCapabilityCompilerPass` collects
   tagged services into the central `CapabilityRegistry`. This mirrors the
   `#[McpTool]` / `mcp.tool` pattern used today.
2. **Payment-handler registration** via the `UcpPaymentHandlerInterface`. Existing
   payment plugins (Stripe, Mollie, Adyen, PayPal, Klarna, …) opt in by
   implementing this interface in addition to the existing
   `PaymentHandlerInterface`. Handlers tagged `ucp.payment_handler` appear
   automatically in the profile.
3. **Profile-builder hook** via the Symfony event `UcpEvents::PROFILE_BUILT`,
   allowing plugins (notably the AP2 Mandates plugin) to inject additional
   capability entries before serialization.

These three extension points are stable contracts from PR #1 onward; everything
else is internal and subject to change while the feature flag is active.

### Capability layering inside the bundle

We adopt a **per-capability folder layout**:

```text
Ucp/Capability/
    Catalog/
    Cart/
    Checkout/
    Order/
    Discount/
    Fulfillment/
    BuyerConsent/
    Loyalty/
    IdentityLinking/
```

Each folder contains the capability class (implementing `UcpCapability`),
its controllers, mappers, and a local README. Capabilities **MUST NOT** depend
on each other directly; cross-capability concerns (e.g. cart-to-checkout
conversion) are coordinated through Symfony events emitted on the central
`Negotiation/NegotiationOrchestrator`.

## Consequences

### Positive

- Clear separation between protocol foundation (`Ucp/`) and commerce domain
  primitives (`Checkout/`, `Customer/`, …) keeps the dependency graph clean.
- The `UCP_SERVER` flag lets us evolve the surface aggressively while the spec
  matures (UCP itself is at protocol version `2026-01-23` and still pre-1.0).
- Per-capability folders make it obvious where to add a new capability and
  enable phased PRs that touch only one folder each.
- The compiler-pass-based extension model is consistent with the established
  Shopware pattern (`mcp.tool`, `flow.action`, `entity.indexer`, …) so plugin
  authors face no new conventions.

### Negative

- A second foundation (`Mcp/` + `Ucp/`) increases the surface area of the
  `Framework/` package. We accept this because the two protocols have
  fundamentally different security models (admin-API/integration-key vs.
  store-API/per-request-signature) and different consumers (operators vs.
  end-user agents).
- A runtime feature flag is a footgun if a misconfigured plugin emits UCP
  traffic with the flag disabled. We mitigate this by failing fast in the
  request resolver and by surfacing the flag state in `debug:ucp`.
- Per-capability folders create some duplication in DI configuration (one
  `services.xml` fragment per capability). This is acceptable given the
  navigability gains.

### Neutral

- Future verticals (Travel, Services) will likely require their own service
  namespace (`dev.ucp.travel.*`). The current layout accommodates this by
  adding sibling folders under `Ucp/Capability/` without restructuring.
- AP2 Mandates remains in a separate plugin repository
  (`swag/ucp-ap2-mandates`) and is not part of the placement decision above.
  The plugin uses the `UcpEvents::PROFILE_BUILT` and `UcpEvents::CHECKOUT_RESPONSE`
  hooks documented as part of the extensibility contract.
