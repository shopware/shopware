# SalesChannel

Store API entry point for content system. Single route orchestrates full pipeline.

## Key Classes

- `AbstractContentRoute` - Abstract route base
- `ContentRoute` - Concrete implementation
- `ContentRouteResponse` - Response wrapper
- `ContentPage` - Response payload (Struct/)

## Endpoint

`POST /store-api/content/{path}` - Matches any path, delegates to ContentRouter.

## Pipeline Orchestration

ContentRoute orchestrates five phases:

1. **Route Matching**: ContentRouter matches URL to content route
2. **Entity Resolution**: EntityIdResolver extracts parameters, queries entities
3. **Layout Resolution**: LayoutResolver determines layout (static or cascade)
4. **Refinement**: RefinedLayoutBuilder builds layout, LayoutRefinery refines
5. **Hydration**: ContentElementHydrator loads data + resolves context

Returns ContentPage containing:
- `rootElement`: Hydrated ContentElement tree
- `resolvedData`: Entity IDs and URL parameters
- `route`: Matched ContentRouteEntity
- `layout`: ContentLayoutEntity

## HTTP Cache

Route decorated with `_httpCache: true`. Response cached based on sales channel, URL, customer group. Invalidation happens when:
- Content routes modified
- Content layouts modified
- Assigned entities modified

Cache tags propagated from entity queries during hydration.

## Error Handling

Returns 404 if:
- No route matches URL
- Entity resolution finds no entity
- Layout resolution finds no layout

ContentSystemException thrown with specific error codes.

## Extension Point

Decorate AbstractContentRoute to modify pipeline (add logging, validation, transformations). Don't break pipeline order - phases must run sequentially.
