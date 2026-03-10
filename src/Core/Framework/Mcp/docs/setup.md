# MCP Server Setup

## Prerequisites
- Shopware 6.7+ (experimental, stable in 6.8)
- `symfony/mcp-bundle` installed (`composer require symfony/mcp-bundle`)

## Enabling the feature

The MCP server is behind an experimental feature flag. Add it to your `.env` file:

```
MCP_SERVER=1
```

Once set, all MCP tools and the `/api/_mcp` endpoint become available.

## Quick start

Create an integration for the MCP client using the existing `integration:create` command:

```bash
bin/console integration:create "MCP Client" --admin
```

This outputs the access key and secret:

```
SHOPWARE_ACCESS_KEY_ID=SWIA...
SHOPWARE_SECRET_ACCESS_KEY=...
```

Then build the MCP client configuration JSON using those credentials:

```json
{
    "mcpServers": {
        "shopware": {
            "type": "streamable-http",
            "url": "https://your-shop.example.com/api/_mcp",
            "headers": {
                "sw-access-key": "SWIA...",
                "sw-secret-access-key": "..."
            }
        }
    }
}
```

### Claude Desktop

Paste the config into `~/Library/Application Support/Claude/claude_desktop_config.json` (macOS) or `%APPDATA%/Claude/claude_desktop_config.json` (Windows).

### Cursor

Paste the config into `.cursor/mcp.json` in your project root.

### First connection

After adding the configuration, your MCP client needs to initialize a session with the Shopware server. The first connection may take a few seconds while Shopware boots its kernel and warms up caches. Subsequent requests are faster. If your client shows "No tools" briefly, wait a moment and refresh -- the server needs to complete its startup.

## Authentication

The MCP endpoint supports two authentication methods:

### Integration credentials (recommended)

Pass `sw-access-key` and `sw-secret-access-key` headers directly. The MCP server validates them via the standard integration credential flow (same as `client_credentials` OAuth grant). No token expiration, no manual token exchange -- credentials work as long as the integration exists. Rate limiting applies to prevent brute-force attempts.

### Bearer token

Standard Admin API OAuth bearer tokens also work. Obtain one via the `/api/oauth/token` endpoint. Note that these tokens expire (default: 10 minutes), so integration credentials are recommended for MCP clients.

## Configuration

The MCP server configuration lives in `config/packages/mcp.yaml`:

```yaml
mcp:
    app: 'Shopware'
    version: '1.0.0'
    client_transports:
        http: true
    http:
        path: /api/_mcp
```

Shopware-specific configuration is under the `shopware.mcp` key:

```yaml
shopware:
    mcp:
        allowed_tools: []                    # Empty = all tools allowed. List tool names to restrict.
        app_tool_timeout: 10                 # Timeout for app webhook tool calls in seconds.
```

## Verifying the setup

Use the debug command to list registered capabilities:

```bash
bin/console debug:mcp
```

## ACL and permissions

All MCP tool operations respect the integration's ACL permissions. To restrict what the MCP client can do:

1. Create an ACL role in the admin with only the desired permissions
2. Assign that role to the integration
3. Omit the `--admin` flag when creating the integration

## Troubleshooting

### Connection refused (ECONNREFUSED) or "fetch failed"

If your MCP client shows errors like `ECONNREFUSED` or "fetch failed", the Shopware server is not running or not reachable at the URL in your MCP config (host and port depend on your setup).

1. Start the Shopware server (e.g. Docker or your usual web setup).
2. Ensure the URL in your MCP config matches how you access the shop (e.g. `http://localhost:<port>/api/_mcp` for local dev).
3. Retry the MCP connection after the server is up.
