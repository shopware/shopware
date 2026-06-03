# Store API MCP

Shopware exposes two MCP surfaces:

- `/api/_mcp` for Admin API automation with Admin API OAuth or integration credentials.
- `/store-api/_mcp` for Store API automation with `sw-access-key` and `sw-context-token`.

The Store API MCP server has its own registry and uses `shopware.store_api_mcp.*` service tags. Admin MCP tools must not appear on `/store-api/_mcp`, and Store API MCP tools must not appear on `/api/_mcp`. Store API MCP tools must live outside the `src/Core/Framework/Mcp` scan path unless the admin MCP discovery config is updated to exclude them.

The initial core tool is `shopware-store-api-context`, which verifies that the MCP request runs with a resolved `SalesChannelContext`. Commerce-specific UCP tools are expected to be registered by plugins against the Store API MCP registry.
