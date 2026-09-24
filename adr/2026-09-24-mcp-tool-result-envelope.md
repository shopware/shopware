---
title: MCP tool result envelope — transitional path to spec shapes
date: 2026-09-24
area: framework
tags: [framework, mcp, ai, tool-result, structuredContent, bc]
---

## Context

Epic [#19965](https://github.com/shopware/shopware/issues/19965). Today Shopware MCP tools largely return a JSON **string** shaped like `{"success": true, "data": …, "_meta": …}` via `McpToolResponse::success()` / `::error()`. `McpToolResponseRule` (PHPStan) forces plugin tools through that helper. The MCP result model is `content[]`, optional `structuredContent`, and `isError`; JSON-RPC errors are reserved for transport failures.

`src/Core/Framework/Mcp/docs/spec-coverage.md` already flags the envelope as undecided (transitional vs long-term) and asks for a consistent business-error mapping. `mcp/sdk` 0.8 makes migration tractable: `ToolReference::extractStructuredContent()` gates on negotiated revision; on 2026-07-28, `outputSchema` / `structuredContent` follow SEP-2106 (any JSON Schema 2020-12 / any JSON value).

This is the **widest blast radius** item in the epic: core tools, plugins (e.g. SwagMcpMerchantTools), apps, agentic-commerce parallels, and [shopware-mcp-evals](https://github.com/shopware/shopware-mcp-evals) all parse `success` / `data`. [#19966](https://github.com/shopware/shopware/issues/19966) needs allowed return shapes named once so ResourceLink typing is not rewritten twice. Sync tool [#20520](https://github.com/shopware/shopware/issues/20520) stays parked until this contract is clear.

**Locked 2026-09-24** for [#19967](https://github.com/shopware/shopware/issues/19967): transitional dual-support → deprecate → remove toward **spec shapes**; drop string `{"success":…}` by **experimental → 6.8.0**. A broader MCP PHPStan guidance pack (Admin ACL ≠ Store auth, reserved groups) is a **follow-on**, not part of merging this ADR.

## Decision

### Path

The Shopware string envelope is **transitional**, not long-term product contract.

1. **Dual-support** — helpers accept / emit both legacy string envelope and native MCP returns during the window.
2. **Deprecate** — UPGRADE / RELEASE_INFO + PHPStan deprecation signal for the string envelope.
3. **Remove** — drop string `{"success":…}` emission and the “must return that string” rule **no later than experimental → 6.8.0**.

Reject indefinite Shopware-only envelope. Reject a hard cut in this iteration while early-adopter parsers still assume `success`.

### Allowed return shapes (named for #19966)

A tool `call` / invoke path may return:

| Shape | Use |
|---|---|
| **Legacy JSON string** `{"success":…}` | Transitional only; dual-read until removed by 6.8.0 |
| **`Content` blocks** (incl. text / resource) | Preferred wire shape; **ResourceLink** is a Content return for large-result offload |
| **`structuredContent`** (+ `content[]` as required by revision) | Machine-readable success payload (maps from today’s `data`) |
| **`isError: true`** with error content / structured payload | Business / domain failure (maps from today’s `success: false` envelope) |
| **JSON-RPC error** | Transport / protocol / unexpected server failure only — not ordinary business validation |

Do not invent a parallel Shopware envelope for ResourceLink or Sync.

### Mapping (legacy → spec)

| Legacy | Spec |
|---|---|
| `success: true` + `data` | `structuredContent` = `data` (and/or text Content summarizing for models); `isError` absent/false |
| `success: false` + error fields | `isError: true`; encode message/details in content / structured error object — **not** a JSON-RPC error |
| `_meta` (incl. offload hints) | Prefer ResourceLink Content for offload; remaining meta follows MCP `_meta` rules for the negotiated revision — do not teach a long-lived prose `_meta.resourceUri` convention |
| Thrown / transport failure | JSON-RPC error |

### `outputSchema`

- Tools **may** declare `outputSchema` describing `structuredContent`.
- Prefer schemas for tools with stable, documented payloads; not mandatory for every tool in the first dual-support PR.
- Handshake vs modern: respect SDK gating (`requiresObjectStructuredContent()`); do not emit modern-only shapes on handshake without dual-era tests.

### `McpToolResponseRule` / PHPStan

- Update the existing rule with the transitional contract (allow listed returns; stop treating string-only as forever).
- **Follow-on (not Wave 1):** MCP PHPStan **guidance pack** — envelope deprecation toward 6.8.0; **Admin ACL required** (audited exceptions); **Store tools ≠ Admin ACL**; reserved-group warn ([#20725](https://github.com/shopware/shopware/issues/20725)). Pointer: Project note / epic child when filed — do not implement the pack in the ADR merge itself.

### Sequencing

| Phase | Work |
|---|---|
| Now | This ADR; unblock ResourceLink typing (#19966) and dual-era honesty |
| Wave 3 | Helper dual-support → tool-by-tool migration → paired [shopware-mcp-evals](https://github.com/shopware/shopware-mcp-evals) dual-read |
| By 6.8.0 | Remove string envelope; tighten PHPStan; UPGRADE final remove notes |
| After ADR / with D3 | PHPStan guidance pack (follow-on) |

## Consequences

- **Clients/agents:** Dual-read `success` **and** `content` / `structuredContent` / `isError` until 6.8.0; then drop string-envelope parsers.
- **Plugins/apps/evals:** External BC — paired Shopware + evals PRs for contract moves; RELEASE_INFO + UPGRADE required for deprecate and remove.
- **Eng:** One helper migration path; ResourceLink lands as Content; Sync (#20520) reuses this contract.
- **PHPStan:** Rule change is part of the contract; guidance pack tracks under the epic as follow-on (Admin ≠ Store).
