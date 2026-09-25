---
title: MCP tool results move from the Shopware envelope to the MCP result format
date: 2026-09-24
area: framework
tags: [framework, mcp, ai, tool-result, bc]
---

## Context

### What MCP defines for a tool result

When a client calls a tool, the MCP specification expects a result with these parts:

- `content`: a list of content blocks the model reads. A block can be text, an image, an embedded resource, or a `resource_link`, which points to a resource the client can fetch later.
- `structuredContent` (optional): the machine-readable result as JSON. A tool can describe its shape with an `outputSchema`, and clients can validate against it.
- `isError` (optional): `true` when the tool ran but failed, for example because of a validation or permission problem. The model sees the error and can react to it.

JSON-RPC errors are separate from all of this. They are meant for protocol problems (unknown tool, invalid parameters, server failure), not for normal business errors.

### What Shopware returns today

Almost all Shopware tools return a JSON string inside a single text block. `McpToolResponse::success()` and `::error()` build it:

```json
{"success": true, "data": {"...": "..."}, "_meta": {"responseSize": 24000}}
{"success": false, "error": "Missing privilege: product:read"}
```

This worked while the ecosystem was young, but it has clear downsides:

- A failed call (`"success": false`) is sent as a normal, successful MCP result. Clients that look at `isError` treat it as a success.
- Clients and models have to parse a JSON string out of a text block instead of reading `structuredContent`. Typed clients can't validate anything, because there is no `outputSchema`.
- The pointer to large results (see the ADR "MCP behaviour on the stateless 2026-07-28 protocol era") travels as a Shopware-specific `_meta.resourceUri` field that models have to be taught, while MCP has `resource_link` for exactly this.

### Who depends on the envelope

Changing it affects more code than any other part of the MCP work:

- Core tools and every plugin or bundle tool that extends `McpToolResponse`.
- App tools. Their webhook or app-script responses follow the same `{success, data}` convention.
- Clients, agent prompts and our evaluation suite (`shopware-mcp-evals`), which read `success` and `data`.

A PHPStan rule, `McpToolResponseRule`, already exists. It only checks that every `#[McpTool]` class extends `McpToolResponse`. It doesn't look at what a tool returns.

The SDK (`mcp/sdk` 0.8) can return content blocks, `structuredContent` and `isError` directly. It also handles one difference between the protocol eras: on the handshake era `structuredContent` must be a JSON object, on the modern era it can be any JSON value.

### Options

1. **Keep the envelope permanently.** No migration, but Shopware stays incompatible with what clients expect, and every new feature (large-result pointers, Sync tool) would extend a format nobody else uses.
2. **Switch now.** Clean, but every client, prompt, app and eval that reads `success` would break at once.
3. **Switch in phases.** Support both formats for a while, deprecate the old one, then remove it.

## Decision

We choose option 3. The Shopware envelope is transitional. It is removed no later than the release in which MCP stops being experimental (6.8.0).

### Phase 1: both formats

`McpToolResponse` fills the MCP fields and keeps the old string for existing readers:

- `structuredContent` contains what `data` contains today.
- A failed call sets `isError: true`.
- The text block still contains the legacy JSON string, so clients and apps that read `success` keep working.

New tools use the new helpers from the start. Existing tools are migrated one by one, together with matching updates in the evaluation suite.

### Phase 2: deprecate

Release notes and upgrade notes announce the removal. A new PHPStan rule reports tools that still build the legacy string themselves. It starts as a warning.

### Phase 3: remove

By 6.8.0 the legacy string is gone. The text block then only contains a short readable summary or the plain result, without the `success` wrapper. The new PHPStan rule becomes an error, then it is removed once nothing can produce the old format anymore.

### How the old format maps to the new one

| Today | After the migration |
|---|---|
| `success: true` with `data` | `structuredContent` = `data`, plus a text block for the model. `isError` is not set. |
| `success: false` with `error` | `isError: true`, with the message in the text block (and optionally a structured error object). Not a JSON-RPC error. |
| `_meta.resourceUri` for large results | A `resource_link` content block. |
| Other `_meta` fields (for example `responseSize`) | MCP `_meta`, following the rules of the negotiated protocol version. |
| Exceptions and protocol failures | JSON-RPC errors, as today. |

### Output schemas

Tools may declare an `outputSchema` for their `structuredContent`. We add them first to tools with a stable, documented result. It isn't mandatory for every tool in the first step. Tools must not send shapes that only the modern era allows (for example a top-level array) to handshake clients. The SDK helps with that, and it is covered by tests on both eras.

### Static analysis

- `McpToolResponseRule` stays as it is: every `#[McpTool]` class extends `McpToolResponse`.
- A second, separate rule checks return values and drives the deprecation in phases 2 and 3. We don't make the existing rule do both, so each rule has one clear job.

Other MCP checks we want in PHPStan are tracked separately and aren't part of this decision: Admin tools must declare ACL privileges, Store tools follow Store API authentication instead of Admin ACL, and extensions must not register tools in reserved groups such as `discovery`.

## Consequences

**For MCP clients and agents.** Until 6.8.0 they can read either format. After that they read `content`, `structuredContent` and `isError` like with any other MCP server. Failures are reported through `isError`, so clients can finally tell them apart from successes.

**For extension developers.** Plugin and bundle tools that use the `McpToolResponse` helpers get the new format automatically. Tools that build the JSON string by hand get a PHPStan warning and must move to the helpers before 6.8.0. App tools keep their response format during phase 1. Their migration follows the same phases and is announced in the release and upgrade notes.

**For Shopware development.** The large-result pointer and the planned Sync tool build on the new format directly, instead of first extending the envelope. Every step that changes what clients see comes with a matching change in the evaluation suite and with release notes.

Related issues: epic #19965, #19967 (this decision), #19966 (large-result pointer), #20520 (Sync tool).
