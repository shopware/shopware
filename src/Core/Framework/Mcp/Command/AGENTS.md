# MCP Commands

## Purpose
CLI commands for inspecting and debugging the MCP server.

## Available commands
- `debug:mcp` -- Lists the registered MCP tools of both servers with their group, handler, dependencies and required privileges

## Usage
```bash
bin/console debug:mcp                             # the tools of both MCP servers, with their governance data
bin/console debug:mcp shopware-entity-search      # full detail for one tool by name
bin/console debug:mcp shopware-context            # full detail for one prompt
bin/console debug:mcp shopware://entities         # full detail for one resource (name or URI)
bin/console debug:mcp --scope=api                 # only the Admin server (/api/_mcp)
bin/console debug:mcp --scope=store-api           # only the Store API server (/store-api/_mcp)
bin/console debug:mcp --integration=SWIA...       # restrict the tool list to what the given integration access key is allowed to see (applies the same allowlist the HTTP endpoint uses)
bin/console debug:mcp --native                    # hand over to the bundle's command (see "Division of labour")
bin/console debug:mcp:native                      # the same command, directly
```

`--tools` is accepted and does nothing: this command only lists tools. It is kept because
[shopware-mcp-evals](https://github.com/shopware/shopware-mcp-evals) invokes
`debug:mcp --tools --no-ansi` in CI and parses the resulting table.

## Division of labour

Two commands, deliberately disjoint jobs. Keep them that way — every row this command renders that
the bundle's already renders is a row to reconcile on the next bundle upgrade, and that bundle is
experimental with no BC promise.

| Question | Command |
|---|---|
| What may this integration call, which toolset hides a tool, which privileges does it need? | `debug:mcp` |
| What is wired where: servers, clients, prompts, resources, capabilities exposed by no server? | `debug:mcp:native` |

This command therefore lists **tools only**. Prompts and resources are listed by the bundle's
command, which shows more for them than this one ever did (description, URI, MIME type). Detail
lookup by name still covers tools, prompts and resources here, because that path reads the registry
directly. Capabilities the bundle assigned to no server are reported in this command's footer too,
since that is how a capability silently disappears and a developer should not need to know about a
second command to find out.

The `Handler` column matches the bundle's vocabulary on purpose, so the two outputs read the same
way.

## Scopes

Shopware runs two independent MCP servers, each with its own builder and registry, both registered by the MCP bundle from the `servers` configuration in `packages/mcp.php`: the Admin server (`mcp.server.admin.builder` / `mcp.server.admin.registry`, tag `mcp.tool`) and the Store API server (`mcp.server.store_api.builder` / `mcp.server.store_api.registry`, tags `shopware.store_api_mcp.tool` **and** `mcp.tool`). `debug:mcp` builds both and prints one block per server, so a tool registered on either endpoint is visible without knowing which one it belongs to.

The MCP bundle ships its own `debug:mcp`, which `McpDebugCommandCompilerPass` moves to `debug:mcp:native` so Shopware's keeps the name. Reach it either directly or with `bin/console debug:mcp --native`; it lists the configured servers and clients and any capability assigned to no server.

Every section heading names the server it belongs to, as a suffix (`Tools (17) [Store API]`, `Prompts (4) [Admin API]`). The block title alone is not enough: with a few dozen tools it scrolls out of view long before the reader reaches the prompts or resources of that same server. The suffix rather than a prefix so the capability kind and count line up down the left edge and read first. Keep it when adding sections.

`--scope` takes the route scope ID of the endpoint (`ApiRouteScope::ID` = `api`, `StoreApiRouteScope::ID` = `store-api`), matching the `_routeScope` the two controllers declare. Omitting it inspects both. A capability lookup by name searches the selected servers in order and the detail view shows a **Scope** row naming the owning server.

`--integration` filters the Admin server only: integration allowlists are resolved from the `sw-access-key` of an Admin integration and are not evaluated for Store API requests. When both servers are listed, the command prints a note saying so instead of silently applying the filter to one half of the output.

## What `debug:mcp` shows

The command uses the same `Registry` instances as the live MCP HTTP endpoints. It calls `Builder::build()` per scope, which runs all loaders and populates each registry identically to what `/api/_mcp` and `/store-api/_mcp` would serve. This means:

- **Core tools**: registered from the `mcp.tool` DI tag, assigned to the Admin API server by the namespace prefixes in `packages/mcp.php`
- **Plugin tools**: registered via the `shopware.mcp.tool` DI tag, re-tagged and assigned to the Admin API server by `McpToolDiscoveryCompilerPass`
- **App tools**: loaded from the database by `AppMcpToolLoader` (requires DB connectivity)
- **Store API tools**: registered from the `shopware.store_api_mcp.tool` and `mcp.tool` DI tags, assigned to the Store API server by its own namespace prefix, listed under the Store API scope

The list view shows five columns: **Name**, **Group**, **Source**, **Dependencies**, and **Privileges**.

- `Group` shows the toolset a tool belongs to, resolved from `#[McpToolGroup]`, the owning app, or a shared name prefix.
- `Source` shows the PHP class name for core/plugin tools and `(app-provided)` for app webhook tools.
- `Dependencies` shows tools declared via `#[McpToolDependsOn]` (comma-separated). Empty means no declared dependencies.
- `Privileges` shows ACL privileges declared via `#[McpToolRequires]` (comma-separated). Static privileges appear as-is (e.g. `system_config:read`); dynamic entity privileges appear as `<entity>:read`. Empty means no declared privileges (informational only — runtime enforcement via `requirePrivilege()` still applies).

If a tool appears under a scope in `debug:mcp`, it is in that endpoint's registry and reachable via `tools/call` there. Whether it is advertised in a bare `tools/list` is a separate question, decided by progressive disclosure: only the `discovery` group is advertised until a client enables the tool's toolset.

## Setting up an MCP client
Use the built-in `integration:create` command to create credentials, then configure your MCP client manually. See `docs/setup.md` for details.
