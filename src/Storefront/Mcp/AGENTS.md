# Storefront MCP Tools

## Why tools live here instead of Core

MCP tools that depend on Storefront-specific services (e.g., `ThemeService`) must live in the Storefront bundle to maintain correct dependency direction. Shopware's architecture requires `Storefront -> Core`, never `Core -> Storefront`.

Core MCP tools live in `src/Core/Framework/Mcp/Tool/` and only depend on Core services. Tools here depend on Storefront services and are registered with the `mcp.tool` tag in `src/Storefront/DependencyInjection/mcp.php`.

`McpToolDiscoveryCompilerPass` in Core also picks up tools tagged `shopware.mcp.tool` from any bundle or plugin, so those are integrated the same way.

## Tools

- `ThemeConfigTool` (`shopware-theme-config`) -- read and update theme configuration (colors, logos, fonts) for a sales channel. Uses `ThemeService` for config retrieval and updates with theme recompilation.

The `salesChannelId` parameter accepts either a UUID or the sales channel name. Agents usually know the name, not the ID, so requiring a UUID made the tool fail on the most natural input. Both are matched in a single query (`sales_channel.id` OR `sales_channel_translation.name`, the latter case-insensitive via the column collation), so neither form shadows the other.

Every unresolvable input returns a `$this->error()` envelope listing the available names. Invalid input must never escape `__invoke()` as an exception: an uncaught throwable reaches the MCP SDK's generic handler and hits the client as an opaque JSON-RPC `-32603`. Unexpected exceptions such as database failures are the exception to that and stay uncaught, per the `McpToolResponse` contract, so they are logged server-side instead of leaking driver or schema details to the client.

## Registration

Services are defined in `src/Storefront/DependencyInjection/mcp.php` with the `mcp.tool` tag — the same tag as Core in-tree bundle tools. The MCP bundle collects it at compile time and assigns the tool to the Admin API server, whose `registry` prefixes in `packages/mcp.php` include `Shopware\Storefront\Mcp\`. MCP config uses PHP DI format (`PhpFileLoader`) for type-safe service definitions, even though the rest of the Storefront bundle still loads XML via `XmlFileLoader`.
