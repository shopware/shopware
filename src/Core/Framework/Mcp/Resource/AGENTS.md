# MCP Resources

## Purpose
MCP resources expose read-only data that AI clients can request. Unlike tools, resources are static data endpoints identified by URIs.

## Current resources
- `EntityListResource` (`shopware://entities`) -- lists all registered entity names
- `BusinessEventsResource` (`shopware://business-events`) -- all business events that can trigger flows
- `FlowActionsResource` (`shopware://flow-actions`) -- all flow actions available in Flow Builder
- `SalesChannelListResource` (`shopware://sales-channels`) -- sales channels with IDs, names, domains
- `CurrencyListResource` (`shopware://currencies`) -- currencies with ISO codes and factors
- `LanguageListResource` (`shopware://languages`) -- languages with locale codes
- `StateMachineResource` (`shopware://state-machines`) -- state machines with states and transitions

## Adding a resource
1. Create a class with `#[McpResource(uri: '...', name: '...', description: '...')]` on the class
2. Return an array with `uri`, `mimeType`, and `text` keys from `__invoke`
3. Register in `mcp.php` with `mcp.resource` and `shopware.feature` tags
