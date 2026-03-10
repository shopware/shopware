# MCP Loaders

## Purpose
Loaders extend the MCP SDK's capability discovery to include tools from Shopware apps.

## Plugin integration
Plugins register MCP tools by tagging services with `shopware.mcp.tool` in their DI XML. The `McpToolCompilerPass` maps these to the `mcp.tool` tag so the MCP SDK discovers them.

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
1. `Mcp::createFromXmlFile()` parses the XML
2. `McpToolPersister` persists tools to `app_mcp_tool` table during app install/update
3. `AppMcpToolLoader` (tagged `mcp.loader`) reads active app tools from DB at server build time
4. Tool calls are proxied to the app webhook via `AppMcpToolExecutor` with HMAC signing

### Response format
App tool responses should follow the same envelope convention as core tools:
```json
{"success": true, "data": {...}}
{"success": false, "error": "message"}
```
`AppMcpToolExecutor` logs a warning when an app response is missing the `success` key.

### Classes
- `AppMcpToolLoader` -- implements `Mcp\Capability\Registry\Loader\LoaderInterface`, reads from DB, registers tools
- `AppMcpToolExecutor` -- sends HMAC-signed HTTP POST to app URL, returns response, validates response convention
