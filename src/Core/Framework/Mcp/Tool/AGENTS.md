# MCP Tools

## Purpose
Each file in this directory is a single MCP tool -- an action that AI clients can invoke via the MCP protocol.

## Naming
- Tool names use `shopware-` prefix with kebab-case: `shopware-entity-search`, `shopware-console-command`
- Class names use PascalCase suffix `Tool`: `EntitySearchTool`, `ConsoleCommandTool`
- Plugin tools: `{plugin-name}-{tool-name}`
- App tools: `{app-name}-{tool-name}`
- Names must only contain `a-zA-Z0-9_-` (no dots) for consistency across all MCP capability types

## Patterns
- Use constructor injection for dependencies
- Place the `#[McpTool]` attribute on the class with `name` and `description`
- Return a JSON string from `__invoke`
- Use `McpContextProvider` to get the authenticated `Context`
- Write operations must accept a `bool $dryRun = true` parameter

## Read tools
- `EntitySchemaTool` (`shopware-entity-schema`) -- entity field/association introspection
- `EntitySearchTool` (`shopware-entity-search`) -- criteria-based search using `RequestCriteriaBuilder`
- `EntityReadTool` (`shopware-entity-read`) -- single entity read by ID
- `SystemConfigReadTool` (`shopware-system-config-read`) -- read shop configuration
- `ApiRoutesTool` (`shopware-api-routes`) -- list API routes
- `BusinessEventsTool` (`shopware-business-events`) -- list available business events
- `FlowActionsTool` (`shopware-flow-actions`) -- list registered flow actions
- `StorefrontSearchTool` (`shopware-storefront-search`) -- search products with sales channel context
- `ConsoleCommandTool` (`shopware-console-command`) -- execute allowlisted console commands

## Write tools
- `EntityUpsertTool` (`shopware-entity-upsert`) -- create/update entities (dryRun wraps in transaction + rollback)
- `EntityDeleteTool` (`shopware-entity-delete`) -- delete entities (dryRun shows cascade impact)
- `SystemConfigWriteTool` (`shopware-system-config-write`) -- update configuration values
- `StateMachineTransitionTool` (`shopware-state-machine-transition`) -- transition entity states

## Adding a new tool
1. Create a class in this directory
2. Add `#[McpTool(name: 'shopware-{tool-name}', description: '...')]` on the class
3. Register in `src/Core/Framework/DependencyInjection/mcp.php` with `mcp.tool` and `shopware.feature` tags
4. Add unit test in `tests/unit/Core/Framework/Mcp/Tool/`
