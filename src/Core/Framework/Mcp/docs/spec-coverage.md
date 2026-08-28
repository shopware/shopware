# MCP Spec Coverage

Reference spec: [Model Context Protocol server specification 2025-11-25](https://modelcontextprotocol.io/specification/2025-11-25/server)

This doc is the compact matrix for one question:

- what the MCP server spec allows
- what Shopware clearly supports today
- what still needs audit before we call the surface stable

The intent is clarity, not false certainty. Where the current state is not verified yet, the table says so.

## How to read this

| Column | Meaning |
|---|---|
| `Spec surface` | Relevant MCP server capability or behavior |
| `What the spec allows` | Short summary of the protocol feature set |
| `Shopware today` | What is clearly implemented or documented in the current Shopware MCP stack |
| `Confidence` | `clear`, `partial`, or `unknown` |
| `Follow-up` | What still needs review, implementation, or documentation |

## Server capability matrix

| Spec surface | What the spec allows | Shopware today | Confidence | Follow-up |
|---|---|---|---|---|
| Tools | `tools/list`, `tools/call`, optional `notifications/tools/list_changed`, paginated `tools/list`, metadata like `title`, `description`, `icons`, `inputSchema`, optional `outputSchema` | Core tools and extension tools exist; `tools/list` and `tools/call` clearly exist; names, descriptions, and input schemas are documented | clear | Audit whether `listChanged` is advertised/emitted and whether Shopware should start using `title`, `icons`, and `outputSchema` |
| Tool results | `content[]`, optional `structuredContent`, optional `isError`, optional `_meta` | Current helpers are text-first and often serialize a Shopware-local JSON envelope such as `{\"success\": true, \"data\": ...}` | clear | Review `McpToolResponse` and related helpers against spec-native `content[]`, `structuredContent`, `isError`, and `outputSchema` |
| Prompts | `prompts/list`, `prompts/get`, optional `notifications/prompts/list_changed`, paginated `prompts/list`, metadata like `title`, `description`, `icons`, prompt arguments | Core `shopware-context` prompt exists; app-backed prompts exist | clear | Audit pagination, `listChanged`, and whether prompt metadata should use more spec fields |
| Resources | `resources/list`, `resources/read`, `resources/templates/list`, optional `resources/subscribe`, optional `resources/unsubscribe`, optional `notifications/resources/updated`, optional `notifications/resources/list_changed`, paginated list operations | Small fixed set of reference resources exists; app-backed resources exist; resources are treated as read-only reference data | partial | Audit `resources/templates/list`, subscriptions, update notifications, metadata fields, and real pagination behavior |
| Utilities: Completion | `completion/complete` for prompt arguments and URI template arguments | Support is not documented clearly enough yet | unknown | Audit `symfony/mcp-bundle` and SDK support, then decide whether Shopware should expose domain-specific completions like entity names or state-machine actions |
| Utilities: Logging | Declared `logging` capability, `logging/setLevel`, `notifications/message` with RFC 5424-style levels | Shopware has an `mcp` Monolog channel, but that is not yet a documented MCP logging utility story | partial | Decide whether Shopware wants real MCP logging utility support; keep product metrics on telemetry, not on log parsing |
| Utilities: Pagination | Opaque cursor pagination on `tools/list`, `prompts/list`, `resources/list`, `resources/templates/list` | Shopware already uses application-level pagination inside some tool payloads, but MCP list pagination is not documented clearly | partial | Verify which MCP list endpoints are actually paginated and align capability advertising and response objects |
| Initialize capability advertisement | `initialize` should only advertise what the server actually supports | Current docs do not pin this down clearly enough | unknown | Audit actual advertised capabilities vs implementation and keep that check in CI or release verification |

## Base protocol topics that still matter

These are not “server feature rows” in the spec navigation, but Shopware still needs to stay honest about them.

| Topic | Shopware today | Confidence | Follow-up |
|---|---|---|---|
| Transport | Streamable HTTP at `/api/_mcp` via `symfony/mcp-bundle` and `McpServerController` | clear | Keep docs aligned with the actual bundle behavior after upgrades |
| Session lifecycle and JSON-RPC routing | Delegated to `symfony/mcp-bundle` / MCP PHP SDK | partial | Document the exact boundary between bundle behavior and Shopware-specific additions |
| Auth and authorization | Shopware adds Admin API auth bridge, ACL, rate limiting, feature flag, app HMAC execution, and MCP tool registration rules | clear | Keep docs and tests aligned with the real enforcement path |

## Client-side spec areas that are not server features here

| Spec area | Why it is out of scope for `/api/_mcp` |
|---|---|
| Client roots | Implemented by MCP clients, not the Shopware server |
| Client sampling | The client or model provider handles this, not the Shopware server |
| Client elicitation | Client-side interactive UX, not a shop server feature |

## Response model review

This is the highest-value alignment topic because it affects every tool.

| Current Shopware pattern | MCP-native alternative | Why it matters | Decision needed |
|---|---|---|---|
| Tool returns a `string` and often serializes `{\"success\": true, \"data\": ..., \"_meta\": ...}` | `content[]` plus optional `structuredContent` and optional `isError` | The current helper is practical, but it is not the same as the protocol’s native result model | Decide whether the current envelope is transitional or long-term |
| No `outputSchema` usage in current docs | Tool definition can advertise `outputSchema` | Better machine-readable contracts for clients and for docs | Decide whether to start adding `outputSchema` for stable tools |
| Business errors often encoded inside the JSON envelope | MCP-native result can mark `isError`, while transport-level failures stay JSON-RPC errors | Cleaner distinction between expected tool errors and protocol failures | Define a consistent error-mapping rule |

## Observability split

This is not purely a spec issue, but it affects how we talk about MCP logging and usage collection.

| Need | Preferred path | Why |
|---|---|---|
| Central adoption metrics across installations | Shopware telemetry abstraction with OpenTelemetry-capable transports | Product and platform need centralized, comparable metrics |
| Request-level support and debugging | Structured logs on the `mcp` channel | Logs are still useful for correlation and support trails |
| MCP protocol logging utility | Optional later capability | Only add it if we actually need MCP `logging/setLevel` and `notifications/message` as protocol features |

## Recommended next checks

| Priority | Check | Outcome |
|---|---|---|
| 1 | Audit actual `initialize` capability advertisement | No over-promising in capability flags |
| 2 | Audit `tools/list`, `prompts/list`, `resources/list`, and `resources/templates/list` for pagination behavior | Honest docs and capability claims |
| 3 | Decide whether Shopware wants real MCP logging utility support or only telemetry plus support logs | Clear logging story |
| 4 | Review `McpToolResponse` against `content[]`, `structuredContent`, `isError`, and `outputSchema` | Cleaner tool result contract |
| 5 | Mirror the final matrix into official docs on `developer.shopware.com/docs` | One canonical public explanation |
