---
title: MCP tool results use an internal result object and one central output mapper
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

The specification also says that a tool returning `structuredContent` SHOULD return the same data serialized as JSON in a text block. A spec-compliant result therefore carries the data twice, and that stays true after any migration.
Clients disagree on which copy reaches the model (as of October 2026):

- Gemini CLI and Cursor pass only `content`.
- Claude Code, Codex CLI and VS Code Copilot pass `structuredContent` and drop the text blocks when it is present.
- ChatGPT passes both.

So neither copy can be dropped without some major client losing the data.

### What Shopware returns today

The tool contract comes from [MCP server placement and extensibility](2026-03-17-mcp-server-placement-and-extensibility.md): a tool's `__invoke()` returns a string built with `$this->success([...])`. This ADR changes that contract.

Almost all Shopware tools return a JSON string, which the SDK wraps into a single text block. `McpToolResponse::success()` and `::error()` build it:

```json
{"success": true, "data": {"...": "..."}, "_meta": {"responseSize": 24000}}
{"success": false, "error": "Missing privilege: product:read"}
```

This worked while the ecosystem was young, but it has clear downsides:

- A failed call (`"success": false`) is sent as a normal, successful MCP result. Clients that look at `isError` treat it as a success.
- Clients and models have to parse a JSON string out of a text block instead of reading `structuredContent`. Typed clients can't validate anything, because there is no `outputSchema`.
- The pointer to large results (see the ADR [MCP behaviour on the stateless 2026-07-28 protocol era](2026-09-24-mcp-stateless-era-degradation-policy.md)) travels as a Shopware-specific `_meta.resourceUri` field that models have to be taught, while MCP has `resource_link` for exactly this.
- Every tool produces the final wire format itself. Any change to that format touches every tool, in core and in every plugin.

The last point matters beyond this migration. MCP is still changing quickly. The 2026-07-28 revision alone changed what `structuredContent` may contain, and further revisions will change result details again. As long as each tool builds the output itself, every such change is a migration for every extension.

### Who depends on the output format

- Core tools and every plugin or bundle tool that extends `McpToolResponse`.
- App tools. Their webhook or app-script responses follow the same `{success, data}` convention.
- Clients, agent prompts and our evaluation suite (`shopware-mcp-evals`), which read `success` and `data`.

A PHPStan rule, `McpToolResponseRule`, already exists. It only checks that every `#[McpTool]` class extends `McpToolResponse`. It doesn't look at what a tool returns.

The SDK (`mcp/sdk` 0.8) passes a complete `CallToolResult` through unchanged. So a single step in core can build the final result, as long as tools hand it something other than a finished string.

### Options

1. **Keep the envelope permanently.** No migration, but Shopware stays incompatible with what clients expect, and every new feature (large-result pointers, Sync tool) extends a format nobody else uses.
2. **Switch every tool to the MCP format now.** Clean, but every client, prompt, app and eval that reads `success` breaks at once. Tools would still build the wire format themselves, so the next spec change would mean the same migration again.
3. **Move tool by tool in phases.** Support both formats, deprecate the old one, remove it. This softens the break, but it keeps the wire format inside every tool.
4. **Separate what a tool returns from what goes on the wire.** Tools return a Shopware result object. One mapper in core turns it into the output format. Phases, and future spec changes, are handled in the mapper.

## Decision

We choose option 4, and serve both formats from it: each client gets the format its negotiated protocol revision supports.

### The internal result object

Tools describe their result, not the wire format. The result object has to outlive more than one output format: the legacy envelope, both MCP protocol eras, and whatever MCP or other agent protocols define next. It is therefore designed around what a result means, not around how MCP spells it today.

**What it holds.** Every part is optional except that a result is either a success or a failure.

| Part | Meaning | Why it is separate |
|---|---|---|
| Data | The machine-readable result: any JSON-serializable value, not only an object | The stateless era (2026-07-28) already allows any JSON value; the handshake era needs an object, and the mapper wraps when needed |
| Summary | A short text for the model or a human ("3 products updated") | Some formats want prose next to the data, others don't. If a tool gives none, the mapper derives the text from the data |
| Error | A failure with a stable code (`invalid_arguments`, `missing_privilege`, `not_found`, `tool_error`), a message and optional details, such as the missing privileges or the invalid fields. `invalid_arguments` means input the tool can't use (malformed JSON, unknown names, unparsable criteria). Well-formed input that breaks a business rule would get a code of its own | Formats differ in how they carry errors (`isError`, error objects, status codes). A stable code can be mapped to all of them. A message alone can't |
| Content parts | Additional typed parts: text, image or other binary data with a MIME type, a link to a resource, an embedded resource | These map one to one to MCP content blocks today and to comparable concepts elsewhere (attachments, artifacts) |
| Links to stored results | A reference to a result that is too large to send inline, with size and MIME type | Whether data goes inline or behind a link is decided by the mapper, based on the size limit of the target format, not by the tool |
| Metadata | Well-known typed fields (pagination, `dryRun`, response size, the echoed query, and the timestamps below) plus a namespaced area for extension-specific values | The mapper decides where and under which names metadata goes, for example MCP `_meta` with its key rules. Extensions can add values without inventing top-level fields |

**Timestamps.** Two well-known metadata fields describe how fresh a result is:

- *Generated at*: when the data was produced. The core sets it when rendering. A tool that returns older data, for example from a search index, sets the actual time of the data instead.
- *Expires at* (optional): until when the result is valid. It is set for stored large results (the expiry of their signed pointer) and by tools whose data is known to go stale.

Renderers pass them on where their format can express them, for example as cache hints once MCP offers those for tool results, and leave them out otherwise. Caching and stale-data handling can then build on them without changing any tool.

**Design rules.**

- **Meaning, not wire names.** No part is named after an MCP field. `isError`, `structuredContent`, `_meta` and `resource_link` exist only inside the mapper.
- **Closed set of part types.** Only core defines part types. An extension can't add a new kind of part, so every renderer can always map every result completely. New part types are added to core together with support in every renderer.
- **Grow by adding, never by changing.** New optional parts or fields may be added. Existing ones keep their meaning. This keeps plugins that build results today valid for later formats.
- **Immutable and complete.** The object is a value object, built by the mapper from the helpers' string today, and returned by tools directly once [#21306](https://github.com/shopware/shopware/issues/21306) lands (or built with a builder for richer results). The mapper only reads it, and nothing downstream depends on a tool having produced JSON.
- **No transport concerns.** Size limits, offloading, pagination cursors of the protocol, and synchronous versus task-based delivery are not part of the object. They belong to the renderer and the protocol layer.

Tools declare `__invoke(): string` today, and PHP checks that return type before the mapper sees the value. So `McpToolResponse::success()` and `::error()` keep returning the legacy JSON string, and the mapper parses it into this object (see *Legacy input* below). That is why tools using these helpers, which is almost all core and plugin tools, don't have to change. Returning the object directly also needs the return declaration changed (`string|McpToolResult`), so it becomes the documented path with [#21306](https://github.com/shopware/shopware/issues/21306), together with the PHPStan rule. `::error()` accepts an optional error code, and the existing helpers such as `missingPrivilegesError()` set one. Tools declare `outputSchema` against the result data, not against a wire format.

### The output mapper

One mapper in core converts every tool result before the SDK sends it. It is the only place that knows the wire format.

- **One renderer per output format.** The mapper picks a renderer for each request: the legacy envelope, the handshake era, the stateless era, and later formats as they appear. The choice depends only on the negotiated protocol revision, not on a feature flag and not only on the era. One table, `McpToolResultCapabilities`, maps a revision to what it supports (the MCP format with `structuredContent` and `resource_link`, object-only `structuredContent`, the inline size limit, the stored format), so a new revision is one entry. `structuredContent` and `resource_link` only exist since 2025-06-18, so clients on 2024-11-05 or 2025-03-26 get the legacy envelope as the only text block, including the pointer to a stored result. Supporting a new format means adding an entry or a renderer, not touching tools.
- **Every renderer handles every part.** A renderer maps each part type to the closest concept of its format. Where a format has no equivalent (for example images in a text-only format), the renderer falls back to a documented text form, never to silently dropping the part.
- **Size limits per format.** The renderer applies the size limit of its format to the complete rendered result, including the text copy the MCP spec asks for. Anything too large is stored and sent as a link, which keeps the tool's summary, its own links and its metadata up to a fixed budget. The limit is 200 KB rendered since 2025-06-18, where the data is sent twice, and 100 KB before, so the offload point is about 100 KB of data on every revision.
- **Format version on the serialized form.** Results that leave PHP and are read back later or elsewhere carry a format version, an enum defined by core: stored large results in `mcp_tool_result_cache`, and responses from app tools. The mapper picks the matching parser for that version, so older stored results and apps built against an older format stay readable. The in-memory result object has no version field: inside PHP the class is the version, and the renderer is chosen by the output target, not by the result. For stored results the version is the `mime_type` column (`McpToolResultStoredFormat`): plain data for clients since 2025-06-18, the legacy envelope before.
- **Legacy input.** The mapper also accepts the legacy JSON string, so tools that return a string, the `McpToolResponse` helpers and app tools keep working. It parses `{success, data, error}` into a result object and renders it like any other result. An `error` may also be an object, as some extension tools return it; its `message` and `code` become the error, and its other keys are kept as details. Keys next to the envelope fields, such as `dryRun` and `preview` of agentic-commerce's UCP tools, are kept as metadata, so a preview can still be told from a committed change. A string that isn't this envelope (no boolean `success`) is passed through unchanged as a single text block, as today.

For MCP today, the renderers fill `structuredContent`, `isError` and the text block, turn links to stored results into `resource_link` blocks, and respect era differences, for example that `structuredContent` must be an object on the handshake era.

**Checking that the format holds up.** A test matrix renders a fixed set of sample results (success with data, failure with an error code, mixed content parts, a large result with a link, metadata from an extension) through every renderer. As a design check, the result parts also have to map onto the result shapes of at least one other agent protocol, for example the artifacts of the Agent2Agent protocol, before the object is finalized. If a sample can't be represented, the object is extended before any renderer is written against it.

### Formats per protocol revision

The negotiated protocol revision picks the format, per request.
There is no feature flag and no date at which the legacy format is removed.

1. **MCP format (2025-06-18 and later).** `structuredContent` contains the result data, a failed call sets `isError: true`, and stored results are linked with `resource_link`.
   The text block contains the result data as plain JSON, without the `success` wrapper.
   That JSON copy stays whenever `structuredContent` is present, because some major clients pass only `content` to the model (see *Context*).
   A summary, if the tool gives one, goes into an additional text block, never in place of the JSON.
   The metadata follows as a text block of its own (`{"_meta": …}`), next to the result's `_meta`: much of it is written for the model (`dryRun`, `total`, the `usage` hint of tool search), and most clients don't pass `_meta` on to it.
   A result without data, such as one stored behind a link, sends no data block.
2. **Legacy envelope (before 2025-06-18), deprecated.** These revisions have neither `structuredContent` nor `resource_link`, so the only text block is the `{"success": …}` string, and the pointer to a stored result is `_meta.resourceUri` inside it.
   The format is deprecated: release notes say so, and new clients are expected to negotiate 2025-06-18 or later.
   It is kept for the clients that still need it, and every client gets only the format it expects.

The tool side is independent of the wire format: the `McpToolResponse` helpers stay, and a PHPStan rule reports tools that build JSON strings by hand ([#21306](https://github.com/shopware/shopware/issues/21306)).

The MCP format doesn't make results smaller than the legacy envelope, because the specification itself asks for the text copy and the envelope is about the same size as that copy.
Large results are kept in check by the size limit and `resource_link`, not by dropping one of the copies.

### How the legacy envelope maps to the MCP format

| Legacy envelope (before 2025-06-18) | MCP format (2025-06-18 and later) |
|---|---|
| `success: true` with `data` | `structuredContent` = `data`, plus the text block. `isError` is not set. |
| `success: false` with `error` | `isError: true`, with the message in the text block (and optionally a structured error object). Not a JSON-RPC error. |
| `_meta.resourceUri` for large results | A `resource_link` content block. |
| Other `_meta` fields (for example `responseSize`) | MCP `_meta`, following the rules of the negotiated protocol version. |
| Exceptions and protocol failures | JSON-RPC errors, as today. |

### Output schemas

Tools may declare an `outputSchema` for their result data. We add them first to tools with a stable, documented result. It isn't mandatory for every tool in the first step. The mapper makes sure a schema-described result is only sent in a shape the negotiated protocol era allows. This is covered by tests on both eras.

### Static analysis

- `McpToolResponseRule` stays as it is: every `#[McpTool]` class extends `McpToolResponse`.
- A second, separate rule reports tools that return a hand-built JSON string instead of the result object. It starts as a warning ([#21306](https://github.com/shopware/shopware/issues/21306)). We don't make the existing rule do both, so each rule has one clear job.

Other MCP checks we want in PHPStan are tracked separately and aren't part of this decision: Admin tools must declare ACL privileges, Store tools follow Store API authentication instead of Admin ACL, and extensions must not register tools in reserved groups such as `discovery`.

## Consequences

**For MCP clients and agents.** Clients on 2025-06-18 and later read `content`, `structuredContent` and `isError` like with any other MCP server, and failures are reported through `isError`, so clients can tell them apart from successes.
Their text block is no longer the `{"success": …}` envelope, so an integration that parses `success` out of the text has to read `isError` and `structuredContent`, or negotiate an older revision.
Clients before 2025-06-18 keep the deprecated envelope unchanged.

**For extension developers.** Plugin and bundle tools that use the `McpToolResponse` helpers get every format change automatically, now and for future MCP revisions. Extensions can attach their own metadata in a namespaced area, but can't invent new part types. Tools that build the JSON string by hand keep working and get a PHPStan warning that points them to the helpers or the result object ([#21306](https://github.com/shopware/shopware/issues/21306)). Declaring `string` as the return type of `__invoke()` keeps working. App tools keep their response format. The mapper translates it, and any change to the app contract is announced separately in the release and upgrade notes.

**For Shopware development.** The mapper is the single place to test the output format on every protocol revision. The large-result pointer and the planned Sync tool build on the result object directly. Every step that changes what clients see comes with a matching change in the evaluation suite and with release notes.

Related issues: epic [#19965](https://github.com/shopware/shopware/issues/19965), [#19967](https://github.com/shopware/shopware/issues/19967) (this decision), [#19966](https://github.com/shopware/shopware/issues/19966) (large-result pointer), [#20520](https://github.com/shopware/shopware/issues/20520) (Sync tool).
