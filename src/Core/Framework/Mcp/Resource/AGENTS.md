# MCP Resources

## Purpose
MCP resources expose read-only data that AI clients can request. Unlike tools, resources are static data endpoints identified by URIs.

## Current resources
- `EntityListResource` (`shopware://entities`) -- lists all registered entity names

## Adding a resource
1. Create a class with `#[McpResource(uri: '...', name: '...', description: '...')]` on `__invoke`
2. Return an array with `uri`, `mimeType`, and `text` keys
3. Register in `mcp.xml` with `mcp.resource` and `shopware.feature` tags
