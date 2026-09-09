---
title: Resolve store-api context token from the storefront session
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
author_github: @mstegmeyer
---
# Core
* Added the `sw-context-source` request header (`PlatformRequest::HEADER_CONTEXT_SOURCE`), with which a store-api client explicitly opts into resolving its context from the storefront session
* Added `Shopware\Core\Framework\Routing\SessionContextTokenAccessor`, the single implementation of the session held context token: the storefront request owns the session (creates it, mints the first token), an opted-in store-api request borrows it under strict conditions
* Added `Shopware\Core\Framework\Routing\SessionContextTokenSubscriber`, which starts the owner's session before routing, follows token rotations into the session through `CustomerLoginEvent`, `CustomerLogoutEvent` and `SalesChannelContextResolvedEvent` (regenerating the session ID), and forces `Cache-Control: private, no-store` on session-resolved store-api responses
* Changed `Shopware\Core\Framework\Routing\SalesChannelRequestContextResolver` to resolve the storefront session's context token when a store-api request declares `sw-context-source: session`
* Added `Shopware\Core\Framework\Routing\RoutingException::sessionContextNotResolvable()` (error code `FRAMEWORK__ROUTING_SESSION_CONTEXT_NOT_RESOLVABLE`, HTTP 400), thrown when a request declares `sw-context-source: session` but the session cannot be used: no session cookie, a cross-site fetch, a shared-cacheable (`_httpCache`) route, an explicit `sw-context-token` header sent alongside, a session that cannot be resumed or holds no token for the sales channel, or the feature being disabled
* Changed `Shopware\Core\Checkout\Customer\Subscriber\CustomerTokenSubscriber` to rotate the session token through `SessionContextTokenAccessor`, so that with `core.systemWideLoginRegistration.isCustomerBoundToSalesChannel` enabled a password change updates the sales-channel-bound token instead of leaving a revoked one behind
* Added the container parameter `shopware.routing.session_context_token.enabled` to disable the store-api side of the behavior
___
# Storefront
* Removed the session handling from `Shopware\Storefront\Framework\Routing\StorefrontSubscriber` (`startSession`, `updateSession`, `updateSessionAfterLogin`, `updateSessionAfterLogout`, `replaceContextToken`); the storefront is now the session owner of the Core implementation. The class no longer depends on `RequestStack` and `SystemConfigService`
* Removed the write-only `sw-sales-channel-id` session key
___
# Upgrade Information
## Store-api can resolve the context from the storefront session
Same-origin browser clients (e.g. an app-based checkout SPA rendered on a storefront page) no longer need the context token in HTML or JavaScript to share the shopper's cart. Requests to store-api that declare `sw-context-source: session`, send the storefront session cookie and the mandatory `sw-access-key` header, and carry no `sw-context-token` header are resolved with the session's context token. Token rotations performed through store-api (login, registration, logout, password change) are written back into the session and regenerate the session ID, so the storefront and the API client stay consistent.

The opt-in header is required and is a contract: a token-less request without it keeps its previous meaning and receives a fresh context, while a request that declares `sw-context-source: session` fails with `FRAMEWORK__ROUTING_SESSION_CONTEXT_NOT_RESOLVABLE` (HTTP 400) whenever the session cannot actually be used - no session cookie, a cross-site fetch, a shared-cacheable (`_httpCache`) route, an explicit `sw-context-token` header sent alongside, or a session that cannot be resumed - instead of silently degrading to a fresh context. Behavior for headless clients is unchanged.

## Storefront session handling moved to Core
The storefront's session and context token handling now lives in `Shopware\Core\Framework\Routing\SessionContextTokenAccessor` / `SessionContextTokenSubscriber`; `StorefrontSubscriber` only keeps the maintenance redirect, the login redirect and the XHR guard. Extensions that decorated or subscribed around `StorefrontSubscriber::updateSession()` should listen to `CustomerLoginEvent`, `CustomerLogoutEvent` or `SalesChannelContextResolvedEvent` instead.
