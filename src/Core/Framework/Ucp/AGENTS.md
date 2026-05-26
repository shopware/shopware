# Shopware UCP Server

## Overview

This module implements the **Universal Commerce Protocol (UCP)** for Shopware. UCP is an open standard that enables agentic commerce platforms (ChatGPT, Perplexity, Gemini, Claude, custom AI agents) to autonomously discover, browse, build carts, and check out at any UCP-compliant business through a uniform interface.

## Status

**Experimental** — gated behind the `UCP_SERVER` feature flag. Use `UCP_SERVER=1` environment variable or `bin/console feature:enable UCP_SERVER` to activate.

See ADR `2026-05-20-ucp-feature-flag-and-bundle-placement.md` for placement rationale and the contract for extension points.

## Architecture

```
Platform (ChatGPT / Perplexity / Gemini / Custom Agent)
    |
    v
+------------------------------------+
|   /.well-known/ucp                 |  WellKnownUcpController
|   (per Sales Channel Domain)      |  -> UcpProfileBuilder
+------------------------------------+
    |
    v  UCP-Agent header + RFC 9421 signature
+------------------------------------+
|   UcpRouteScope routes              |  UcpAgentRequestResolver
|   /ucp/v1/*                          |  -> NegotiationOrchestrator
+------------------------------------+
    |
    v
+------------------------------------+
|   Capability controllers            |  CartController, CatalogController,
|   (one per UCP capability)          |  CheckoutController, ...
+------------------------------------+
    |
    v  delegated to existing Store-API routes
+------------------------------------+
|   Shopware Store API / DAL          |
+------------------------------------+
```

## Folder structure

- `Api/` — Admin-API endpoints for configuration and operations
- `Capability/` — One folder per UCP capability (Cart, Catalog, Checkout, Order, Discount, Fulfillment, BuyerConsent, Loyalty, IdentityLinking)
- `Command/` — Symfony console commands (`ucp:debug`, `ucp:keys:list`, `ucp:keys:create`, `ucp:keys:rotate`, `ucp:keys:retire`, `ucp:keys:reencrypt`)
- `Conformance/` — Non-production helpers (fixture seed command, conformance checkout helper, conformance webhook emitter). Loaded ONLY in `dev`/`test`; controllers reach this code through optional DI args that resolve to `null` in `prod`.
- `DataAbstractionLayer/` — Entity definitions, entities, collections
- `DependencyInjection/` — Compiler passes (capability and payment-handler tag scanners)
- `Discovery/` — `/.well-known/ucp` controller, profile builder, supported-versions registry
- `Jwt/` — EC keypair generation and storage abstraction
- `Negotiation/` — Capability intersection orchestrator + per-request context object
- `Payment/` — Payment handler interface, registry, default adapters
- `Profile/` — Platform profile fetcher, validator, discovery budget enforcer
- `Resources/config/routes/` — Routing definitions
- `ScheduledTask/` — Key retirement, cache cleanup, session cleanup
- `Security/` — Private key encryption + Monolog guard
- `Transport/Rest/` — REST transport plumbing (route scope, agent header parsing, response envelope, exception listener)
- `Transport/Signature/` — RFC 9421 builder/verifier, RFC 9530 content-digest

## UCP Capabilities

| Identifier                                | Type      | Status in Core |
|-------------------------------------------|-----------|----------------|
| `dev.ucp.shopping.catalog.search`         | root      | implemented    |
| `dev.ucp.shopping.catalog.lookup`         | root      | implemented    |
| `dev.ucp.shopping.cart`                   | root      | implemented    |
| `dev.ucp.shopping.checkout`               | root      | implemented    |
| `dev.ucp.shopping.order`                  | root      | implemented    |
| `dev.ucp.shopping.discount`               | extension | implemented    |
| `dev.ucp.shopping.fulfillment`            | extension | implemented    |
| `dev.ucp.shopping.buyer_consent`          | extension | implemented    |
| `dev.ucp.shopping.loyalty`                | extension | hook only (plugin-provided) |
| `dev.ucp.common.identity_linking`         | root      | implemented (OAuth-AS per Sales Channel) |
| `dev.ucp.shopping.ap2_mandate`            | extension | external plugin `swag/ucp-ap2-mandates` |

## Extension points

Three stable contracts for plugin authors:

### 1. Capability registration (`#[McpTool]`-style)

Plugins implement `Shopware\Core\Framework\Ucp\Capability\UcpCapability` and register their service with the DI tag `ucp.capability` (with required `name` attribute). The `UcpCapabilityCompilerPass` collects them at compile time.

```xml
<service id="MyVendor\MyPlugin\MyCustomCapability">
    <tag name="ucp.capability" name="com.mycompany.shopping.my_custom"/>
</service>
```

### 2. Payment handler registration

Existing payment plugins (Stripe, Mollie, …) opt in by additionally implementing `Shopware\Core\Framework\Ucp\Payment\UcpPaymentHandlerInterface` and tagging the service with `ucp.payment_handler`.

```xml
<service id="MyVendor\Stripe\StripeUcpHandler">
    <tag name="ucp.payment_handler" name_id="com.stripe.tokenizer"/>
</service>
```

### 3. Profile-built event hook

Plugins subscribe to `UcpEvents::PROFILE_BUILT` (`ucp.profile.built`) to mutate the rendered profile array (e.g. for the AP2 mandates plugin).

## Conventions

- All classes use `@experimental stableVersion:v6.8.0 feature:UCP_SERVER` annotation
- All classes use `#[Package('framework')]` attribute
- All routes are gated by `Feature::isActive('UCP_SERVER')` at the request resolver layer
- Logging uses the `ucp` Monolog channel
- Private key material is encrypted at rest via `PrivateKeyEncryptor` (AES-256-GCM, HKDF-SHA256-derived key from `APP_SECRET`)
- The `KeyMaterialGuard` Monolog processor strips known private-key context keys defensively
- Capability names follow UCP's reverse-domain naming: `{reverse-domain}.{service}.{capability}`

## Security

See ADR `2026-05-20-ucp-jwt-key-storage-and-rotation.md` for the full key threat model. Headline points:

- HTTPS-only profile fetching, no redirects, SSRF guard against private IP ranges
- RFC 9421 signatures verified with strict timing (clock-skew tolerance 60 s)
- RFC 9530 content-digest verified on every signed request
- Per-Sales-Channel key isolation; cascading delete on `sales_channel` row removal
- Discovery budget enforces global rate limit + per-origin backoff on failure
- Three dedicated ACL privileges: `ucp.viewer`, `ucp.editor`, `ucp.key_rotator`

## Future ideas / backlog

- **MCP transport for UCP** (`/ucp/mcp`) — leverages the existing `symfony/mcp-bundle`; mirrors the REST surface
- **A2A and Embedded transports** — optional follow-up PRs
- **HSM/KMS-backed key storage** — `UcpSigningKeyRepositoryInterface` is ready for a non-DAL backend
- **Cross-Sales-Channel SSO** — currently each channel has its own OAuth AS for identity linking; future ADR may add a federation layer
