# MCP Tools

## Purpose
Each file in this directory is a single MCP tool -- an action that AI clients can invoke via the MCP protocol.

## Naming
- Tool names use `shopware-` prefix with kebab-case: `shopware-entity-search`, `shopware-entity-upsert`
- Class names use PascalCase suffix `Tool`: `EntitySearchTool`, `EntityUpsertTool`
- Plugin tools: `{plugin-name}-{tool-name}`
- App tools: `{app-name}-{tool-name}`
- Names must only contain `a-zA-Z0-9_-` (no dots) for consistency across all MCP capability types

## Patterns
- Use constructor injection for dependencies
- Place the `#[McpTool]` attribute on the class with `name` and `description`
- Extend `McpToolResponse` and return via `$this->success()` or `$this->error()` from `__invoke`
- Use `McpContextProvider` to get the authenticated `Context`
- Write operations must accept a `bool $dryRun = true` parameter. The `executeWithDryRun` helper adds `SKIP_TRIGGER_FLOW` to the context to prevent Flow Builder actions from firing during preview, and rolls back the transaction afterward
- Entity tools must validate entity existence with `$this->registry->has($entity)` before ACL checks to provide clear "entity not found" messages
- Entity tools that return DAL data must inject `JsonEntityEncoder` and use it instead of `jsonSerialize()` to respect `includes`/`excludes`
- Entity tools returning DAL data should use the `McpEntityIncludes` trait and call `applyDefaultIncludes()` to keep responses compact (see below)

## Description quality (LLM routing)

The `description` argument on `#[McpTool]` is what the agent reads to pick a tool. It is a **routing surface**, not a docblock. Lessons captured from the GPT-4o eval suite:

- **Lead with the user's trigger phrases.** A description that opens with "The correct tool for count, sum, average, and other aggregate questions" routes correctly when the user asks "how many products?". A description that opens with "Run aggregations over any Shopware entity" does not.
- **Use negative phrasing to break ties.** When two tools share keywords, the description must spell out the contrast: `"Use this — NOT shopware-entity-search — for any 'how many'…"` is more decisive than a positive description alone.
- **Do not reference other tools as prerequisites unless they truly are.** Phrases like "Use shopware-foo-read to check current values first" train the agent to call the read tool even when the user explicitly asked to write. Declare prerequisites with `#[McpToolDependsOn]`, not in prose.
- **Mention the use cases the user will name.** If a prompt is "upload this image as a product cover", the description should contain the phrase "product cover" and clarify that no extra parameter is needed for that case. Otherwise the agent often returns no tool selection at all.
- **Make required parameters truly required.** A parameter without a PHP default ends up `required: true` in the JSON schema. If users frequently won't supply it (a sales channel UUID, a tax ID), give it a default of `''` or `null` and validate inside `__invoke()`. GPT-4o refuses to call tools when required parameters are missing from the prompt — even when the description says they are optional.

### Cache after description changes

Tool descriptions are read at container compile time and baked into the cached DI container. Editing a `#[McpTool(description: ...)]` attribute does **not** take effect until you run `bin/console cache:clear`. If `bin/console debug:mcp` or `tools/list` shows the old text, clear the cache first before debugging further.

## Tool dependencies (`#[McpToolDependsOn]`)

When one tool only makes sense if the AI has already used another tool first, declare that relationship with `#[McpToolDependsOn]`:

```php
use Shopware\Core\Framework\Mcp\Attribute\McpToolDependsOn;

#[McpTool(name: 'shopware-entity-delete', description: '...')]
#[McpToolDependsOn('shopware-entity-search')]
class EntityDeleteTool extends McpToolResponse { ... }
```

- The attribute is **repeatable** — add multiple `#[McpToolDependsOn]` lines if the tool depends on several others.
- Dependencies are **tool-only** — tools can only depend on other tools, not on prompts or resources.
- The `McpToolCompilerPass` resolves dependencies transitively and stores the result in the `shopware.mcp.tool_dependencies` container parameter, which the allowlist provider uses at runtime.
- **Allowlist auto-expansion:** when a user enables a tool in the Admin integration UI, all its declared dependencies (and their transitive dependencies) are automatically added to the allowlist. Removing a tool does **not** auto-remove its dependencies — they may be intentionally enabled independently.
- `bin/console debug:mcp` shows the resolved dependencies in a **Dependencies** column.

### Current dependency graph (core + merchant plugin)

| Tool | Depends on |
|---|---|
| `shopware-entity-read` | `shopware-entity-schema` |
| `shopware-entity-search` | `shopware-entity-schema` |
| `shopware-entity-aggregate` | `shopware-entity-schema` |
| `shopware-entity-upsert` | `shopware-entity-schema` |
| `shopware-entity-delete` | `shopware-entity-search` (→ `shopware-entity-schema`) |
| `shopware-system-config-write` | `shopware-system-config-read` |
| `merchant-cart-checkout` | `merchant-cart-manage` |

**Rule:** only add `#[McpToolDependsOn]` when the dependency is genuinely required to use the tool — not just convenient. Unnecessary dependencies inflate every integration's allowlist.

## Declaring required privileges (`#[McpToolRequires]`)

When a tool requires specific ACL privileges to run, annotate it with `#[McpToolRequires]`. This is **declarative only** — it does NOT add a new enforcement layer. Runtime enforcement still depends on `requirePrivilege()` calls inside `__invoke()` and the DAL's own ACL checks.

The attribute serves as operator documentation: `debug:mcp` shows it in the **Privileges** column, the `/_action/mcp/capabilities` API exposes it in `requiredPrivileges`, and the Admin UI uses it to warn when an integration's assigned role is missing privileges.

Two forms:

**Static privilege** (known at compile time):
```php
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;

#[McpTool(name: 'shopware-system-config-read', description: '...')]
#[McpToolRequires('system_config:read')]
class SystemConfigReadTool extends McpToolResponse { ... }
```

**Dynamic privilege** (entity name comes from a runtime parameter):
```php
#[McpTool(name: 'shopware-entity-read', description: '...')]
#[McpToolRequires(entityParam: 'entity', operations: ['read'])]
class EntityReadTool extends McpToolResponse { ... }
```

- The attribute is **repeatable** — add multiple `#[McpToolRequires]` lines when a tool needs more than one privilege.
- Use `entityParam` + `operations` for entity tools where the privilege depends on the `$entity` parameter passed at runtime. The Admin UI renders this as `<entity>:read` with a tooltip.
- Still call `$this->requirePrivilege($context, 'system_config:read')` (or the equivalent) inside `__invoke()` for actual runtime enforcement.

### Current privilege declarations (core tools)

| Tool | Required privileges |
|---|---|
| `shopware-system-config-read` | `system_config:read` |
| `shopware-system-config-write` | `system_config:update` |
| `shopware-order-state` | `order:read`, `order:update`, `order_transaction:update`, `order_delivery:update` |
| `shopware-media-upload` | `media:create`, `product:update` |
| `shopware-entity-read` | `<entity>:read` (dynamic) |
| `shopware-entity-search` | `<entity>:read` (dynamic) |
| `shopware-entity-aggregate` | `<entity>:read` (dynamic) |
| `shopware-entity-upsert` | `<entity>:create`, `<entity>:update` (dynamic) |
| `shopware-entity-delete` | `<entity>:delete` (dynamic) |
| `shopware-theme-config` (Storefront) | `theme:read`, `theme:update` |

## Response format convention
All tools must extend the `McpToolResponse` abstract class. It provides two helpers:

**Success**: `$this->success(array $data, array $meta = [])`
```json
{"success": true, "data": [...], "_meta": {"total": 42, "page": 1}}
```

**Error**: `$this->error(string $message)`
```json
{"success": false, "error": "Human-readable message"}
```

Rules:
- `success` (bool) is always present at root
- `data` holds the actual result (structure is tool-specific)
- `_meta` is optional, used for pagination (`total`, `page`, `limit`), context (`salesChannelId`), and write metadata (`dryRun`)
- `error` (string) only appears when `success` is false
- Responses up to 100 KB are returned inline. Responses at or above 20 KB include `_meta.responseSize` so the LLM can see the cost of the current call and learn to use tighter `includes`/`limit` next time
- Responses larger than 100 KB are stored in `mcp_tool_result_cache` (session-scoped) and returned as a `shopware://tool-result/{uuid}` resource URI in `_meta.resourceUri`. The full content is fetched via `resources/read` (handled by `ToolResultResource`). `_meta.query` echoes the originating tool name and arguments so the LLM can disambiguate which call produced which URI. Cache rows are wiped on session DELETE by `McpSessionCleanupSubscriber`. When no MCP session is active (CLI/test), the response falls back to inline delivery
- A PHPStan rule (`McpToolResponseRule`) enforces that all `#[McpTool]` classes extend the abstract class

## Pagination with shopware-entity-search

`EntitySearchTool` exposes `limit` (default 25) and `page` (default 1) as top-level parameters alongside the criteria JSON. Every response includes a `_meta` block:

```json
{ "success": true, "data": [...], "_meta": { "total": 1482, "page": 1, "limit": 25 } }
```

To iterate through all results, increment `page` until `page * limit >= total`:

- Page 1: `page=1` → records 1–25
- Page 2: `page=2` → records 26–50
- Total pages: `ceil(_meta.total / _meta.limit)`

You can also set `limit` inside the criteria JSON string directly — the parameter only applies when the criteria does not already contain a `limit` key (`??=`).

**Count mode:** The tool defaults to `total-count-mode: exact`, which runs a separate `COUNT(*)` query so `_meta.total` always reflects the real dataset size. You can override this in the criteria JSON to `next-pages` (faster — fetches `limit * 6 + 1` rows to detect if a next page exists, but total is not meaningful) or `none` (no count query at all — fastest, but `_meta.total` only reflects the current page size).

## Search vs. aggregate: why they are separate tools

`EntitySearchTool` and `EntityAggregateTool` look similar but serve different purposes and have different output sizes:

| | `shopware-entity-search` | `shopware-entity-aggregate` |
|---|---|---|
| Returns | Entity records | Aggregation results only |
| Entity rows | Up to `limit` (default 25) | Always 0 (`limit: 0` internally) |
| Aggregations in response | Never | Always |
| Response risk | Large on wide entities | Bounded — no row data |
| Typical use | "Show me the last 10 orders" | "How many opt-in newsletter recipients?" |

**Why not combine them?** Entity rows can already fill most of the inline 100 KB budget for typical searches. Bucket aggregations (like `terms` on product names or `date-histogram` by day) can produce hundreds or thousands of result entries on their own. Mixing both in one response means a single oversized result is much more likely to spill out of the inline budget into the resource cache, hiding the actual data behind a `resourceUri` round-trip. Separate tools remove the ambiguity: `entity-search` is always bounded by `limit`, `entity-aggregate` is always bounded by the aggregation definitions.

**Rule:** Never add aggregation output to `EntitySearchTool`. If you need both records and metrics for the same entity, make two sequential tool calls.

## Smart default includes (McpEntityIncludes trait)

Entity tools (`EntitySearchTool`, `EntityReadTool`, and the plugin's `StorefrontSearchTool`) auto-apply `includes` when the caller hasn't specified them. This dramatically reduces response size by only serializing the fields AI clients actually need.

**How it works:**
1. The tool introspects the `EntityDefinition` to find all scalar fields (id, name, price, etc.)
2. Only associations explicitly requested in the criteria are included
3. Unrequested auto-loaded associations (thumbnails, extensions, translated duplicates) are stripped
4. The `translated` pseudo-field is always injected for entities with `TranslatedField` instances, ensuring inherited/resolved values (e.g. variant product names) are never lost
5. The caller can always override by passing their own `includes` in the criteria -- `translated` is still auto-injected

**Usage in tools:**
```php
use McpEntityIncludes;

// After building the criteria, before searching -- single call handles everything
$this->applyDefaultIncludes($definition, $criteriaObj);
```

`applyDefaultIncludes()` handles two cases:
- **No includes provided**: builds smart defaults from the definition (scalar fields + requested associations + `translated`)
- **User-provided includes**: injects `translated` into the includes for any entity with translated fields, recursing into loaded associations

All entity read tools use `JsonEntityEncoder` for serialization (not the Store API serializer), so `includes`/`excludes` filtering works consistently regardless of whether the data comes from a regular or sales channel repository.

## Read tools
- `EntitySchemaTool` (`shopware-entity-schema`) -- entity field/association introspection
- `EntitySearchTool` (`shopware-entity-search`) -- criteria-based search; returns records only, **never aggregations** (see below)
- `EntityAggregateTool` (`shopware-entity-aggregate`) -- aggregation-only queries (`limit: 0` internally, no entity rows in response)
- `EntityReadTool` (`shopware-entity-read`) -- single entity read by ID
- `SystemConfigReadTool` (`shopware-system-config-read`) -- read shop configuration

## Write tools
- `EntityUpsertTool` (`shopware-entity-upsert`) -- create/update entities (dryRun wraps in transaction + rollback)
- `EntityDeleteTool` (`shopware-entity-delete`) -- delete entities (dryRun shows cascade impact)
- `SystemConfigWriteTool` (`shopware-system-config-write`) -- update configuration values
- `OrderStateTool` (`shopware-order-state`) -- change the state of an order, its transactions, and/or deliveries in one call
- `MediaUploadTool` (`shopware-media-upload`) -- upload media from URL, optionally assign to product as cover image

## Merchant workflow tools (plugin)
Higher-level workflow tools for merchant operations live in the `SwagMcpMerchantAssistant` plugin (`custom/plugins/SwagMcpMerchantAssistant`), not in core. This separation keeps core tools focused on platform primitives while allowing merchant-specific tools to evolve independently.

Plugin tools are registered via `shopware.mcp.tool` DI tag and use the `merchant-*` name prefix.

## Error handling for extension developers
Tools extending `McpToolResponse` benefit from built-in error handling:
- `executeWithDryRun()` catches any `\Throwable` and returns it as a structured `$this->error()` response
- Unhandled exceptions from `__invoke()` produce a generic MCP error (`-32603`). Prefer catching known exceptions and returning `$this->error($message)` instead.
- Write tools should validate inputs before the operation (e.g., `SystemConfigWriteTool` rejects null values, entity tools validate entity existence)

## Adding a new tool
1. Create a class in this directory
2. Add `#[McpTool(name: 'shopware-{tool-name}', description: '...')]` on the class
3. If the tool only works after using another tool first, add `#[McpToolDependsOn('other-tool-name')]` (repeatable)
4. If the tool requires specific ACL privileges, add `#[McpToolRequires('privilege:operation')]` (repeatable); for entity tools use `#[McpToolRequires(entityParam: 'entity', operations: ['read'])]`. Still call `$this->requirePrivilege()` inside `__invoke()` for actual runtime enforcement.
5. Extend `McpToolResponse` and return via `$this->success()` / `$this->error()`
6. For entity tools: validate with `$this->registry->has($entity)` before ACL checks
7. Register in `src/Core/Framework/DependencyInjection/mcp.php` with `mcp.tool` and `shopware.feature` (flag: `MCP_SERVER`) tags
8. Add unit test in `tests/unit/Core/Framework/Mcp/Tool/`
9. Add the tool name to `expectedTools()` in `McpCapabilityDiscoveryTest` (see below)

## Validating that a tool is actually reachable

How registration works differs between core tools and plugin tools:

| Tool location | Registration mechanism | What can go wrong |
|---|---|---|
| Core (`src/Core/Framework/Mcp/Tool/`) | `mcp.tool` DI tag + directory in `mcp.yaml` `scan_dirs` | Missing tag **or** missing scan_dir silently drops the tool |
| Plugin (`shopware.mcp.tool` DI tag) | `McpToolCompilerPass` calls `addTool()` at compile time | Missing tag; wrong tag name; attribute on method instead of class |

**For core tools**, `McpCapabilityDiscoveryTest` (`tests/integration/Core/Framework/Mcp/McpCapabilityDiscoveryTest.php`) is the authoritative check. It boots the full kernel, calls the live `/api/_mcp` endpoint, and asserts every expected capability name is present. Add new core tool names to its `expectedTools()` list.

**For plugin tools**, the `McpToolCompilerPass` handles HTTP registration automatically from the DI tag — no `scan_dirs` entry is needed. A missing `#[McpTool]` attribute (or attribute placed on `__invoke()` instead of the class) means the compiler pass cannot extract the tool name and the tool is silently skipped.

Quick manual check: `bin/console debug:mcp` uses the same registry as the HTTP endpoint and shows core tools, plugin tools, and app tools in one view.
