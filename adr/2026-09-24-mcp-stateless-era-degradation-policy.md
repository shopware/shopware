---
title: MCP per-feature degradation policy for the modern (stateless) era
date: 2026-09-24
area: framework
tags: [framework, mcp, ai, protocol, degradation, dual-era]
---

## Context

Epic [#19965](https://github.com/shopware/shopware/issues/19965) adopts `mcp/sdk` 0.8 dual-era serving. Both Admin (`/api/_mcp`) and Store (`/store-api/_mcp`) are still pinned with `Builder::withoutModernEra()`. The modern revision (2026-07-28) is **stateless**: `Mcp-Session-Id` is ignored, `DELETE` returns 405, and each request gets a fresh in-memory session.

Nine call sites, two tables, and DELETE-only cleanup still hang off durable session state, in three clusters:

| Cluster | Feature | Risk on modern without a written policy |
|---|---|---|
| A | Mid-session toolset enable | Enable does not stick; clients look connected but see the wrong tool surface |
| B | `tools/list_changed` delivery | Notifications never reach a durable session |
| C | Large-result offload (`mcp_tool_result_cache`) | Session-keyed lookup / DELETE GC fail; silent inline fallback or orphan rows |

Connect-time `?toolsets=` already shipped ([#20509](https://github.com/shopware/shopware/pull/20509) / [#20504](https://github.com/shopware/shopware/issues/20504)). Related locks: signed short-lived offload tokens + ResourceLink ([#19966](https://github.com/shopware/shopware/issues/19966)); dual-serve both endpoints after gates ([#19969](https://github.com/shopware/shopware/issues/19969)); elicitUrl complements dryRun and needs `request_state` on modern ([#19972](https://github.com/shopware/shopware/issues/19972)).

Without one written rule, Admin and Store drift, and dual-era tests ([#20513](https://github.com/shopware/shopware/issues/20513)) cannot assert an agreed degradation.

**Locked 2026-09-24** for [#20512](https://github.com/shopware/shopware/issues/20512): request-derived visibility; Admin and Store move together; mid-session enable is handshake-only on modern.

## Decision

### Rule (future-proof)

**Derive what a request can see from the request itself** (URL `?toolsets=`, credential / principal, and ACL), **never from server-held connection state.**

Admin and Store **move together** under this rule. No second visibility model for Store.

### Per-feature answers

| Feature | Decision | Modern-era behaviour |
|---|---|---|
| **Toolset visibility (cluster A)** | Request-derived | Supported path is `?toolsets=` (plus allowlist / ACL). Mid-session `shopware-toolset-enable` remains **handshake-only**; do not claim it works on modern. |
| **`list_changed` (cluster B)** | Demoted; not load-bearing for progressive disclosure | After unpin, prefer SDK notification bus ([#19970](https://github.com/shopware/shopware/issues/19970)) on a shared pool ([#19980](https://github.com/shopware/shopware/issues/19980)). Clients that never re-list must use `?toolsets=`. Do not invent durable modern session state just to keep list_changed. |
| **Large-result offload (cluster C)** | Honest on both eras via principal-scoped tokens | Re-key off `session_id`. Authorize `resources/read` with **signed short-lived offload tokens** (not raw OAuth `jti`). Ship ResourceLink ([#19966](https://github.com/shopware/shopware/issues/19966)). TTL GC mandatory ([#20511](https://github.com/shopware/shopware/issues/20511)). Cleanup must not assume a session store or DELETE. |
| **`McpSessionIdValidator`** | Handshake-era | Dead / no-op on a modern-only request path; do not reintroduce session validation as a modern gate. |
| **Session DELETE cleanup** | Handshake-era only | Modern DELETE → 405. The age-based TTL task ([#20511](https://github.com/shopware/shopware/issues/20511)) covers `mcp_tool_result_cache`. `mcp_toolset_session` rows are only written on the handshake era and keep the session-liveness cleanup task. |

### Endpoints

`/api/_mcp` and `/store-api/_mcp` share this degradation table. Isolated session registries stay; visibility and offload **policy** do not fork.

### elicitUrl vs pin order

Do **not** stay pinned solely because tools may call back into the client. For [#19972](https://github.com/shopware/shopware/issues/19972): implement / document modern `request_state` (HMAC, shared across workers) **before** advertising URL elicitation on modern. elicitUrl **complements** `dryRun` (default safety stays); no Admin confirmation UX as part of epic [#19965](https://github.com/shopware/shopware/issues/19965). Dual-serve ([#19969](https://github.com/shopware/shopware/issues/19969)) is the adoption path; a later **noticed handshake-removal** is deferred (no date set yet).

### Explicit non-answers

- Do **not** degrade silently (enable that appears to work but does not stick).
- Do **not** keep marketing mid-session enable as the modern progressive-disclosure path.
- Do **not** choose handshake-only offload as the end state of this epic (rejected in favour of signed tokens).

## Consequences

- **Clients:** `?toolsets=` is the supported progressive-disclosure path on both endpoints. Handshake clients keep mid-session enable until a future noticed handshake-removal.
- **Plugins/apps:** One visibility model; no Admin/Store split for “what can this connection see?”
- **Ops / eng:** Shared helpers and dual-era fixtures ([#20513](https://github.com/shopware/shopware/issues/20513)) assert this table. Unblocks honest [#19969](https://github.com/shopware/shopware/issues/19969) dual-serve after Cluster C path + TTL + tests + evals session-less init.
- **Storage:** `ToolResultCacheStorage` drops durable `session_id` as the offload authority; token claims + TTL replace DELETE-centric GC for modern.
- **Docs:** Update `src/Core/Framework/Mcp/AGENTS.md` / `docs/spec-coverage.md` when implementation lands; UPGRADE only if public claims about enable/offload change for early adopters.
