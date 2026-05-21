---
title: UCP sales-channel binding and profile resolution
date: 2026-05-20
area: framework
tags: [framework, ucp, sales-channel, discovery, well-known, multi-tenant]
---

## Context

Shopware operates a multi-channel model: every installation can host any number
of independent **Sales Channels**, each with its own domains, languages, tax
regions, payment methods, shipping rules, product catalog scoping, and customer
groups. A single installation commonly serves B2C storefronts, B2B portals,
headless integrations, and mobile apps side by side.

UCP, on the other hand, models a **business** as a single entity that publishes
one profile at `/.well-known/ucp` and offers one set of endpoints. The UCP
specification has no native concept of "channels".

We must decide how to reconcile these two models:

1. **One profile per installation** — global capabilities, single set of payment
   handlers and signing keys.
2. **One profile per Sales Channel** — each channel independently UCP-enabled
   with its own capabilities, handlers, and keys.
3. **One profile per Sales Channel Domain** — finest granularity, allowing
   per-region/per-language variants.

The decision also affects the OAuth 2.0 Authorization Server topology in the
Identity Linking capability — a global AS vs. one AS per Sales Channel has
direct consequences on customer-account separation, consent UX, and scope
enforcement.

## Decision

### One UCP profile per Sales Channel, served per Sales Channel Domain

Each Sales Channel is independently UCP-enabled. UCP is **opt-in** per Sales
Channel via the Admin UI; activating UCP on Sales Channel A does not affect
Sales Channel B in any way.

The `/.well-known/ucp` endpoint is resolved relative to the **Sales Channel
Domain** that received the request. The existing `RequestTransformer` and
`SalesChannelDomainSubscriber` machinery already maps an incoming `Host` header
to its owning Sales Channel; the UCP controller reuses that resolution to
select the correct profile.

When multiple `SalesChannelDomain` entries belong to the same Sales Channel,
they all serve the **same** profile by default. A profile-strategy column on
the `ucp_sales_channel_config` entity (`profile_uri_strategy = 'domain' |
'config'`) controls this:

- `domain` (default) — one profile per Sales Channel, served on every domain
- `config` — explicit per-domain profile via `custom_profile_uri`, useful when
  the operator wants different protocol versions or capability subsets per
  region (rare, but supported)

### New configuration entity

`ucp_sales_channel_config`:

| Column                    | Purpose                                                        |
| :------------------------ | :------------------------------------------------------------- |
| `id`                      | Binary UUID                                                    |
| `sales_channel_id`        | FK, unique, cascading delete                                   |
| `active`                  | UCP enabled/disabled toggle                                    |
| `ucp_version`             | Protocol version this channel advertises (e.g. `2026-01-23`)   |
| `profile_uri_strategy`    | `domain` or `config`                                           |
| `custom_profile_uri`      | Override URI when `strategy = config`                          |
| `enabled_capabilities`    | JSON array of activated capability identifiers                 |
| `enabled_transports`      | JSON array — `rest`, `mcp`, `a2a`, `embedded`                  |
| `continue_url_template`   | Fallback URL template for `continue_url` (default = storefront) |
| `platform_allowlist`      | JSON or null — null means permissionless onboarding allowed    |
| `discovery_budget`        | JSON — cache size, rate limit, backoff parameters              |
| `webhook_url_override`    | Test/staging override for the platform-provided webhook URL    |

All UCP-related entities (`ucp_signing_key`, `ucp_platform_profile_cache`,
`ucp_negotiation_session`) reference `sales_channel_id` directly. Sales-Channel
deletion cascades to all UCP rows.

### Resolution algorithm

For an inbound request to `/.well-known/ucp`:

1. `RequestTransformer` maps the `Host` header to a `SalesChannelDomain`.
2. `UcpProfileResolver::resolve($request, $domain)` looks up the corresponding
   `ucp_sales_channel_config` row.
3. If `active = false` or no row exists: respond `404 Not Found`. The host has
   no UCP profile.
4. If `profile_uri_strategy = 'domain'`: build and serve the channel-level
   profile.
5. If `profile_uri_strategy = 'config'`: build and serve the per-domain
   profile pointed to by `custom_profile_uri`.

For an inbound request to a UCP capability endpoint (`/ucp/v1/*`, `/ucp/mcp`,
`/ucp/oauth/*`):

1. `UcpRequestContextResolver` extracts the Sales Channel from the `Host` /
   `sw-sales-channel-id` header (the same resolution chain used by the
   Store API).
2. The resolved `SalesChannelContext` carries an
   `ucp.sales_channel_config` extension attached by
   `UcpSalesChannelContextExtensionLoader`.
3. Capability controllers validate the operation is in the channel's
   `enabled_capabilities`; otherwise respond `404`.

### OAuth Authorization Server

Identity Linking instantiates one OAuth 2.0 Authorization Server **per Sales
Channel**. Each AS has its own:

- Client repository scoped to that channel
- Scope vocabulary (defaults plus channel-specific custom scopes)
- Signing key (sharing the same `ucp_signing_key` table)
- Discovery document at
  `/ucp/v1/.well-known/oauth-authorization-server` on the channel's domain

This is required because:

- Customers in Shopware are scoped to a Sales Channel; a token granted on
  channel A must not grant access to channel B.
- Consent screens are themed by the channel's storefront theme.
- Channel-specific compliance requirements (e.g. B2B vs. B2C) can dictate
  different scope grants.

### Multi-domain caveat

When a Sales Channel has multiple domains and operates in `domain` strategy,
the profile served is identical on each domain — including the signing keys.
This is acceptable because UCP profile URLs are stable identifiers; the
profile's `endpoint` field is resolved against the same domain that served the
profile, so traffic stays on the requesting domain.

If an operator needs truly distinct identities per domain, they configure
`profile_uri_strategy = 'config'` and point each domain at a separate
version-specific profile under `/.well-known/ucp/{slug}`.

## Consequences

### Positive

- The Sales Channel abstraction maps cleanly onto a UCP "business". Existing
  channel-scoped artefacts (payment methods, shipping methods, customer
  groups, country sets, tax rules) carry forward into UCP responses without
  custom mapping logic.
- Operators can run a UCP-enabled B2C channel alongside an UCP-disabled B2B
  channel without conflict.
- Headless setups without a storefront can still serve UCP: the
  `continue_url_template` admin field lets them point to a custom fallback UI.
- The OAuth AS topology matches existing customer scoping, eliminating
  cross-channel-leak threat classes.
- Per-channel signing keys enable operators to revoke a single channel's
  participation without affecting others.

### Negative

- Operators with very many channels (e.g. enterprises with one channel per
  country) must enable and configure UCP per channel. We mitigate this with
  a bulk-enable admin action and a `bin/console ucp:install --all-channels`
  CLI command.
- Per-channel OAuth ASes mean a customer with accounts on multiple channels
  must consent separately for each channel. This is consistent with the
  current Shopware customer model but is a step backward from a hypothetical
  single-sign-on flow. A future ADR may introduce cross-channel SSO; we
  consider that orthogonal to UCP.
- The `profile_uri_strategy = 'config'` escape hatch adds complexity for the
  uncommon case. We keep it because the alternative (forcing all
  multi-domain channels into identical profiles) blocks legitimate
  multi-region deployments.

### Neutral

- The same machinery (Sales Channel resolution via `Host` header) is already
  used by the Store API and Storefront; reusing it imposes no new
  infrastructure requirements.
- For the (rare) case where a single Sales Channel needs to advertise
  multiple protocol versions concurrently, `supported_versions` in the
  profile handles it. The Sales Channel binding stays unchanged.
