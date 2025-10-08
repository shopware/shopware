# Router

Route matching orchestration. ContentRouter delegates to RouteCollectionBuilder (DB query) and ContentRouteMatcher (pattern matching).

## Why Not Standard Symfony Router

Routes stored in DB table, not config files. RouteCollectionBuilder queries `content_route` table per request and converts rows to Symfony RouteCollection. ContentRouteMatcher wraps Symfony UrlMatcher for pattern matching.

## Performance Critical

RouteCollectionBuilder hits DB on every request. Cache the compiled RouteCollection in production or performance will be unacceptable. Building RouteCollection from DB is expensive.

## Key Classes

- `ContentRouter` - Entry point, calls builder then matcher
- `RouteCollectionBuilder` - Queries `content_route`, builds Symfony RouteCollection
- `ContentRouteMatcher` - Wraps Symfony UrlMatcher, returns RouteMatchResult

## Pattern Matching

Uses Symfony's standard pattern matching algorithm. When multiple routes match the same URL, priority field determines selection (sorted DESC during collection build). Routes with equal priority and specificity produce non-deterministic results.

## Data Flow

```
ContentRouter::match($pathInfo)
  → RouteCollectionBuilder::build() → DB query
  → ContentRouteMatcher::match($pathInfo, $routes) → Symfony UrlMatcher
  → RouteMatchResult (matched route + parameters)
```

RouteMatchResult contains matched ContentRouteEntity and extracted URL parameters. Null if no match found.
