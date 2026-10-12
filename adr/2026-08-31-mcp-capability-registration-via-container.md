---
title: MCP capability registration via the container
date: 2026-08-31
area: framework
tags: [framework, mcp, ai, extensibility, plugin, dependency-injection]
---

## Context

[2026-03-17 - MCP server placement and extensibility](2026-03-17-mcp-server-placement-and-extensibility.md)
settled where MCP capabilities live (core vs plugin vs app) and named the registration and discovery
mechanism as public API: `mcp.yaml` `scan_dirs`, the DI tags, and `McpToolDiscoveryCompilerPass`.

The `scan_dirs` half of that mechanism no longer exists. `symfony/mcp-bundle` 0.12 replaced the MCP
SDK's file-based discovery with compile-time container registration, and 0.13 added support for
several MCP servers per application, each declaring which capabilities it exposes. Shopware runs two
servers — `/api/_mcp` and `/store-api/_mcp` — so the upgrade forced a decision about how a capability
reaches the right one.

The placement model from that ADR is unaffected and still stands. Only the registration mechanism is
superseded here.

## Decision

A capability is registered by its DI tag alone, and assigned to exactly one server.

### Registration

Every MCP capability is a container service carrying an MCP attribute (`#[McpTool]`,
`#[McpPrompt]`, `#[McpResource]`, `#[McpResourceTemplate]`) and the matching DI tag (`mcp.tool`,
`mcp.prompt`, `mcp.resource`, `mcp.resource_template`). The bundle collects those at compile time,
derives the input schema, and registers them on a server builder. No directory is scanned, so the
`discovery.scan_dirs` option is gone and a capability's location on disk no longer matters.

The Shopware-owned tags stay exactly as they were: plugins and third-party bundles use
`shopware.mcp.tool` / `.prompt` / `.resource`, Store API capabilities use
`shopware.store_api_mcp.*`, and `McpToolDiscoveryCompilerPass` re-tags them. This ADR changes
nothing for extension authors.

### Server assignment

Each server lists the capabilities it exposes in `packages/mcp.php` under
`mcp.servers.<name>.registry`, as namespace prefixes:

- `admin` — `Shopware\Core\Framework\Mcp\`, plus `Shopware\Storefront\Mcp\` when that bundle is installed
- `store_api` — `Shopware\Core\System\SalesChannel\Mcp\`

Prefixes cannot express plugin or third-party bundle capabilities, whose namespace is arbitrary, and
the `*` wildcard is not usable because it would also claim the other server's capabilities. Those are
therefore assigned explicitly: `McpToolDiscoveryCompilerPass` appends the class name to the bundle's
`mcp.servers.elements` parameter — the channel the bundle's own compiler pass reads its per-server
lists from — before that pass runs. Plugin capabilities go to the Admin API server, matching where
they were registered before.

A capability assigned to no server is silently not registered. `bin/console debug:mcp --native`
reports those, so the failure mode is diagnosable.

Store API tools carry `mcp.tool` in addition to `shopware.store_api_mcp.tool`, because the bundle
only collects the SDK tags. The Store API namespace prefix keeps them off the Admin API server, and
`shopware.store_api_mcp.tool` remains the scope marker the analysis passes read.

### What stays out of the bundle's hands

Three things the `servers` configuration cannot express are applied by
`McpServerBuilderCompilerPass`:

- **Protocol handlers are scoped per server.** The bundle wires `addRequestHandlers()` from one
  global tag for every server, but Shopware's handlers are bound to one registry — both
  `McpAllowlistListRequestHandler` instances answer a `ListToolsRequest`, so a shared tag would let
  the Admin API handler answer on the Store API endpoint. The tags are `mcp.admin.request_handler`
  and `mcp.store_api.request_handler`.
- **Capability loaders stay on the Admin API server.** App capabilities have always been an Admin
  API concern; the bundle's global `addLoaders()` would newly advertise every app tool on
  `/store-api/_mcp`.
- **Both servers page with `shopware.mcp.pagination_limit`,** so the number the allowlist request
  handlers slice with cannot drift from the one the SDK advertises.

## Consequences

- An in-tree bundle capability needs one thing instead of two: the DI tag. The old failure mode of a
  correctly tagged tool silently missing because its directory was not in `scan_dirs` is gone.
- It is replaced by a narrower one: a capability in a namespace no server names is not registered.
  That is what `debug:mcp --native` exists to surface, and it only affects core and in-tree bundles,
  since plugin capabilities are assigned explicitly.
- Moving a core capability to a namespace outside the configured prefixes now requires updating
  `packages/mcp.php`. Adding a namespace there is cheap; a prefix that matches nothing is a fatal
  container error in the bundle, so it cannot rot unnoticed.
- Shopware depends on the bundle's `mcp.servers.elements` parameter, which is bundle-internal. It is
  the mechanism the bundle itself uses to hand per-server lists to its compiler pass, and there is no
  public alternative for arbitrary-namespace capabilities. The coupling is a known cost of keeping
  plugin extensibility, and it is covered by unit tests on the assignment.
- Both servers own separate session stores, because session IDs are not namespaced per server: a
  shared store would make a session minted on one endpoint valid on the other. Consequently anything
  reading session liveness across endpoints — `McpToolsetSessionCleanupTaskHandler` — has to consult
  every store.
