---
title: Resolve store-api context token from the storefront session
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
author_github: @mstegmeyer
---
# Core
* Added the `sw-context-source` request header (`PlatformRequest::HEADER_CONTEXT_SOURCE`), with which a store-api client explicitly opts into resolving its context from the storefront session
* Added `Shopware\Core\Framework\Routing\SessionContextTokenAccessor`, which reads and writes the storefront session's context token for opted-in same-origin store-api requests
* Changed `Shopware\Core\Framework\Routing\SalesChannelRequestContextResolver` to fall back to the storefront session's context token when a store-api request declares `sw-context-source: session` and carries no `sw-context-token` header but an existing same-origin storefront session cookie
* Added `Shopware\Core\Framework\Api\EventListener\SessionContextTokenSyncListener`, which writes context token rotations (e.g. guest registration) back into the storefront session, regenerates the session ID on such a rotation, and forces `Cache-Control: private, no-store` on session-resolved store-api responses
* Added the container parameter `shopware.routing.session_context_token.enabled` to disable the behavior
___
# Upgrade Information
## Store-api can resolve the context from the storefront session
Same-origin browser clients (e.g. an app-based checkout SPA rendered on a storefront page) no longer need the context token in HTML or JavaScript to share the shopper's cart. Requests to store-api that declare `sw-context-source: session`, send the storefront session cookie and the mandatory `sw-access-key` header, and carry no `sw-context-token` header are resolved with the session's context token. Token rotations performed through store-api are written back into the session and regenerate the session ID, so the storefront and the API client stay consistent.

The opt-in header is required: a token-less request without it keeps its previous meaning and receives a fresh context. An explicit `sw-context-token` header always takes precedence; behavior for headless clients is unchanged. Routes eligible for shared HTTP caching (`_httpCache`) never consult the session.
