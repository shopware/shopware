---
title: Caching Strategy for Store API
date: 2025-09-15
area: framework
tags: [caching, store-api, performance, core]
---

## Context

Store API performance is critical for all headless installations. Currently, Store API responses are not
cached by default, which leads to unnecessary server load and reduced performance for end users.

At the same time, Storefront (classical Shopware frontend based on twig templates, mentioned here and further in
this doc only for context and strategy alignment) already has a caching mechanism in place and Shopware provides
a reference configuration for reverse proxies like Varnish, that supports Storefront caching.

The goal is to introduce a caching strategy for Store API, while reusing existing approaches and keeping required changes
on the client and infrastructure side to a minimum.

Important details:
  - Store API response may differ by context (e.g. logged in customer, currency, language, active rules, etc).
  - Storefront uses cookies to track differences in the contexts.
  - Storefront marks cacheable routes via the `'_httpCache' => true` route defaults attribute. If this attribute is
    present (and other conditions are met), `CacheResponseSubscriber` adds `Cache-Control: public, s-maxage=7200` header
    to the response (TTL is controlled via configuration).
  - Storefront's `Cache-Control` header is prepared for reverse-proxy (only when reverse proxy is enabled
    in the configuration). Client-side caching remains `no-cache, private` as we lack the ability to invalidate client
    caches, unlike reverse proxies.
  - Storefront caches may be invalidated via cache tags (x-tag in Varnish).
  - Several non-mutating Store API endpoints that return non-sensitive data use `POST` to support larger payloads
    (see Criteria object). Some of them already support both `POST` and `GET`.
  - `RequestCriteriaBuilder` supports building Criteria from separate query parameters for `GET`
    requests (`filter`, `grouping`, `fields`, `page`, `limit`, etc). Criteria as a separate parameters leads
    to a more complex OpenAPI schema. Also differences between standard and php array query parameters serialization
    makes clients implementation more complex (`colors[]=red&colors[]=blue` vs `colors=red,blue`).

## Decision

1. HTTP methods
   - Prefer `GET` for non-mutating endpoints returning non-sensitive data.
   - For endpoints that currently use `POST` but are non-mutating and return non-sensitive data, add `GET` support
     (of fully transition to `GET` if possible).
2. Criteria in query parameters
   - Introduce `_criteria` parameter to support passing Criteria in `GET` requests as a single query parameter.
   - Format: JSON -> gzip -> base64url (url-safe Base64) of the Criteria object.
   - Provide SDK helpers for encoding/decoding and canonicalization (stable key ordering, normalized arrays) to improve
     cache hit ratio across clients.
   - Introduce phaseout plan for separate criteria parameters (e.g. `filter`, `grouping`, `fields`, `page`, `limit`, etc).
3. Use cache headers (not cookies) to differentiate contexts on Store API
   - Use `sw-currency-id` and `sw-language-id` to differentiate currency and language. Shopware must update currency and
     language ids in the context of the current request based on these headers.
   - Use `sw-context-hash` to differentiate other context aspects (e.g. logged in customer, active rules, etc). Use the
     same algorithm as for storefront context hash cookie.
   - All three headers must be returned in the Store API responses. If clients want to utilize caching, they should
     send these headers in subsequent requests. When client sends these headers, reverse proxy uses them to detect
     the cache bucket. Additionally, the language and currency headers change the currency and language in the response.
   - `Vary` header should include all three headers, so reverse proxies and CDNs can differentiate cache entries.
4. Mark cacheable routes
   - Use `'_httpCache'` route default attribute to mark cacheable Store API routes.
   - Extend `CacheResponseSubscriber` to add `Cache-Control` header to Store API responses similar
     to Storefront responses, but ignoring cookies (relying on headers + `Vary` instead). Default `Cache-Control`
     value for cacheable routes should be `public, max-age=0, s-maxage=1800, stale-while-revalidate=86400, stale-if-error=7200`,
     non-cacheable `no-cache, private`
5. Invalidation strategy
   - Reuse existing cache tags implementation to invalidate cached Store API responses.

## Consequences

- SDK updates
   - Switch relevant endpoints to GET where safe
   - Add support for `_criteria` parameter (encoding, decoding, canonicalization)
   - Implement automatic request method selection (fallback to POST when the compressed _criteria would exceed practical
     URL limits).
   - Track and resend `sw-currency-id`, `sw-language-id`, and `sw-context-hash` headers on subsequent requests.
- Clients
   - No changes required if caching is not desired.
   - To utilize caching, clients should adopt the updated SDK or implement the same strategy.
   - Client should beware that `sw-currency-id`, `sw-language-id` can change the response language and currency.
- Extensions 
   - Custom Store API endpoints can opt into caching using the same route flags.
- Trade-offs
   - Using compressed `_criteria` reduces request readability and makes debugging and logging harder.
   - Without canonicalization, compressed `_criteria` parameter may lead to a decreased cache hit ratio.
   - Operators may need minor reverse-proxy adjustments, depending on the level of customization.

## Considered alternatives
1. Keep using cookies for context differentiation similarly to Storefront:
   - Less explicit for the clients, cache can be used unintentionally.
   - More complex configuration for reverse proxies (e.g. Varnish).
   - No need to change the client implementation.
   Rejected in favor of explicit request headers.

2. Cache POST requests with big payloads if possible:
   - Not aligned with HTTP semantics (POST is not cacheable by default).
   - More complex configuration for reverse proxies and CDNs.
   - Not transparent for the clients that caching is used.
   - Transparent request - easier logging and debugging.
    Rejected in favor of alignment with HTTP semantics and minimal changes on infra side were preferred.

3. Two-step flow: POST returns a request hash; GET retrieves cached data by hash:
   - More complex implementation for clients (changed workflow).
   - More complex implementation for Shopware (need to store request hashes and map them to actual requests).
   - Additional round-trip for the requests.
   - Transparent request - easier to debug and log.
    Rejected in favor of simplicity of implementation, limited number of requests and minimal changes on clients side.

4. Use “plain” structured query parameters (filter[...][]=...)
   - Can hit url length limits more easily.
   - More complex OpenAPI schema, problem with array format differences between clients persists.
   - Transparent request - easier to debug and log.
    Rejected in favor of more compact representation and simpler OpenAPI schema.
