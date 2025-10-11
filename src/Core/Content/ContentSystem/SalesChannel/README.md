# SalesChannel

Store API entry point for content system. Single route orchestrates full pipeline.

## Key Classes

- `AbstractContentRoute` - Abstract route base
- `ContentRoute` - Concrete implementation
- `ContentRouteResponse` - Response wrapper

## Endpoint

`POST /store-api/content/{path}?elementId={id}` - Optional `elementId` for partial rendering.

## Query Parameters

The endpoint accepts optional query parameters:
- `elementId`: String - Request only specific element and its descendants (partial rendering)

## Pipeline Orchestration

ContentRoute orchestrates six phases:

1. **Route Matching**: ContentRouter matches URL to content route
2. **Entity Resolution**: EntityIdResolver extracts parameters, queries entities
3. **Layout Resolution**: LayoutResolver determines layout (static or cascade)
4. **Refinement**: RefinedLayoutBuilder builds layout, LayoutRefinery refines
5. **Hydration**: ContentElementHydrator loads data + resolves context
6. **Output Processing**: SubTreeExtractor for partial rendering (if elementId present)

Returns ContentPage containing:
- `layoutId`: Layout UUID
- `layout`: Hydrated ContentElement tree (root element)
- `layoutName`: Layout name
- `layoutVersion`: Layout version ID
- `route`: Matched ContentRouteEntity

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
