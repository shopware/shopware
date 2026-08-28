# MCP Loaders

## Purpose
Loaders extend the MCP SDK's capability discovery to include tools, prompts, and resources from Shopware apps. Three concrete loaders (`AppMcpToolLoader`, `AppMcpPromptLoader`, `AppMcpResourceLoader`) extend `AbstractAppMcpLoader`, which provides the shared try/fetch/foreach pipeline plus `capabilityName()` (prefixes the app name) and `resolveDescription()` helpers.

## Plugin integration
Plugins register MCP tools by tagging services with `shopware.mcp.tool` in their DI XML. At compile time, the `McpToolCompilerPass` does two things:

1. Re-tags the service `shopware.mcp.tool` → `mcp.tool` so it is wired into the DI container and service locator.
2. Reads the `#[McpTool]` attribute via reflection and adds a `addTool($className, $toolName)` method call on `mcp.server.builder` so the tool appears in the live HTTP registry — not just in `debug:mcp`.

This means plugins do **not** need a `scan_dirs` entry in `mcp.yaml`. Plugin lifecycle is fully respected: the service only exists in the container when the plugin is installed and active.

## App integration
Apps declare tools in `Resources/mcp.xml`:

```xml
<mcp xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <mcp-tools>
        <mcp-tool name="sync-orders" url="https://app.example.com/mcp/sync-orders">
            <label>Sync Orders</label>
            <label lang="de-DE">Bestellungen synchronisieren</label>
            <input-schema>
                <property name="since" type="string" description="ISO date" required="true"/>
            </input-schema>
        </mcp-tool>
    </mcp-tools>
</mcp>
```

### Pipeline
1. `Mcp::createFromXmlFile()` parses the XML (uses `XmlUtils::loadFile()` for XXE-safe loading)
2. `McpToolPersister` persists tools to `app_mcp_tool` table during app install/update
3. `AppMcpToolLoader` (tagged `mcp.loader`) reads active app tools from DB at server build time
4. Tool calls are proxied to the app webhook via `AppMcpCapabilityExecutor` with HMAC signing

### Reserved name enforcement
App tool names are automatically prefixed with the app name (e.g., `my-erp-sync-orders`). If the resulting name starts with `shopware-`, the tool is silently skipped and a warning is logged. This prevents apps from overriding built-in core tools.

### Response format
App tool responses should follow the same envelope convention as core tools:
```json
{"success": true, "data": {...}}
{"success": false, "error": "message"}
```
`AppMcpCapabilityExecutor` logs a warning when an app response is missing the `success` key.

### Webhook payload
The `AppMcpCapabilityExecutor` sends a JSON POST body with this structure:
```json
{
  "tool": "my-erp-sync-orders",
  "arguments": { ... },
  "source": {
    "url": "https://shop.example.com",
    "shopId": "abc123",
    "appVersion": "1.2.0"
  }
}
```
- `shopId` identifies the Shopware instance (from `ShopIdProvider`)
- `appVersion` is the installed version of the app
- The request is signed with HMAC-SHA256 via `RequestSigner`
- Successful executions are logged at `debug` level; failures at `error` level

### Internal URL dispatch (serverless app scripts)

App tools whose `url` starts with `/` are dispatched as Symfony subrequests instead of outbound Guzzle calls. This enables apps to serve tool logic via `/api/script/{path}` without an external server.

**How `AppMcpCapabilityExecutor` routes calls:**
- URL starts with `/` → `executeSubRequest()`: matches the route, creates a POST subrequest, passes `arguments` as a form parameter, inherits auth headers from the parent MCP request, returns the response body.
- URL starts with `http`/`https` → existing HMAC-signed Guzzle POST to external webhook. HMAC signing is skipped when `appSecret` is null (apps without `<setup>` that use internal URLs).

**Why POST form params, not JSON body:** Shopware's `JsonRequestTransformer` middleware only runs on the main request. In a subrequest, a raw JSON body is not parsed into the `request` ParameterBag. Passing `['arguments' => $arguments]` as form data ensures `hook.request.request.all('arguments')` returns the array in Twig scripts.

**SQL filter:** `AppMcpToolLoader` loads apps where `app_secret IS NOT NULL OR t.url LIKE '/%'` -- apps without a registration secret are included when their tool URL is internal.

### Classes
- `AbstractAppMcpLoader` -- base class implementing `LoaderInterface`: wraps the DB fetch in a try/catch, iterates rows, and provides `capabilityName()` / `resolveDescription()` helpers for concrete loaders
- `AppMcpToolLoader` -- reads from `app_mcp_tool`, registers tools, enforces reserved `shopware-` prefix, honors the `shopware.mcp.allowed_tools` compile-time allowlist
- `AppMcpPromptLoader` -- reads from `app_mcp_prompt`, registers prompts
- `AppMcpResourceLoader` -- reads from `app_mcp_resource`, registers resources
- `AppMcpCapabilityExecutor` -- branches on URL prefix: `/` → subrequest (no HMAC); `http(s)://` → HMAC-signed Guzzle POST. Used by all three loaders to invoke app capabilities. Returns response body as JSON string.
