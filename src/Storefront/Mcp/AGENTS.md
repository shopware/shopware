# Storefront MCP Tools

## Why tools live here instead of Core

MCP tools that depend on Storefront-specific services (e.g., `ThemeService`) must live in the Storefront bundle to maintain correct dependency direction. Shopware's architecture requires `Storefront -> Core`, never `Core -> Storefront`.

Core MCP tools live in `src/Core/Framework/Mcp/Tool/` and only depend on Core services. Tools here depend on Storefront services and are registered with the `mcp.tool` tag in `src/Storefront/DependencyInjection/mcp.php`.

The `McpToolCompilerPass` in Core discovers tools tagged `shopware.mcp.tool` from any bundle or plugin, so these tools are seamlessly integrated into the MCP server.

## Tools

- `ThemeConfigTool` (`shopware-theme-config`) -- read and update theme configuration (colors, logos, fonts) for a sales channel. Uses `ThemeService` for config retrieval and updates with theme recompilation.

## Registration

Services are defined in `src/Storefront/DependencyInjection/mcp.php` with the `mcp.tool` tag (collected via `tagged_iterator('mcp.tool')` in Core's `mcp.php` — same tag as Core in-tree bundle tools). MCP config uses PHP DI format (`PhpFileLoader`) for type-safe service definitions, even though the rest of the Storefront bundle still loads XML via `XmlFileLoader`.
