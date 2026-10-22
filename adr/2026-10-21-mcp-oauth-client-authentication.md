---
title: OAuth authentication of MCP clients on the Admin API
date: 2026-10-21
area: framework
tags: [framework, mcp, ai, oauth, admin-api, security]
---

## Context

### What MCP clients expect

Clients such as Claude, ChatGPT, Cursor and Claude Code can log in to an MCP server without any copied secret.
The user enters only the server URL.
The client gets a `401`, discovers the authorization server, opens the browser, the user logs in and approves access, and the client receives a token.
The [MCP authorization specification (2026-07-28)](https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization) defines this flow on top of OAuth 2.1:

- The MCP server is an OAuth resource server.
  It MUST serve Protected Resource Metadata (RFC 9728) and point to it from the `WWW-Authenticate` header of its `401` answers, or from a well-known URI.
- The authorization server MUST offer Authorization Server Metadata (RFC 8414) or OpenID Connect discovery.
  For an issuer with a path, such as `https://shop.example/api`, clients first try `https://shop.example/.well-known/oauth-authorization-server/api`.
- Clients MUST send the RFC 8707 `resource` parameter, and the MCP server MUST only accept tokens issued for it as audience.
  Passing a token on to other services is forbidden.
- Authorization servers SHOULD support Client ID Metadata Documents (CIMD).
  Dynamic Client Registration (RFC 7591) is deprecated.
- Authorization servers SHOULD send `iss` in the authorization response (RFC 9207).

### What Shopware has today

The authorization code grant with PKCE from [#20277](https://github.com/shopware/shopware/pull/20277) (6.7.15.0) provides most of the authorization server:

- `OAuthAuthorizeController` serves `GET /api/oauth/authorize`, forwards to the Administration consent page, and completes the request in `POST /api/oauth/authorize` for the logged-in user.
  It passes on only `response_type`, `client_id`, `redirect_uri`, `state`, `scope`, `code_challenge` and `code_challenge_method`; a `resource` parameter is dropped.
- Public clients live in `shopware.api.oauth_clients` (`Configuration.php`), keyed by client id with a `name` and `redirect_uris`.
  The default is `shopware-cli` with `http://127.0.0.1/callback` and `http://[::1]/callback`.
  `PublicClientRegistry` allows any port on loopback redirect URIs (RFC 8252) and exact matches otherwise.
- Access tokens are JWTs valid for `access_token_ttl` (`PT10M`), refresh tokens for `refresh_token_ttl` (`P1W`), with rotation and reuse detection per token family.
  `AccessTokenRepository::isAccessTokenRevoked()` always returns `false`; access tokens are only invalidated by expiry or by a user credential change (`SymfonyBearerTokenValidator::validateAccessTokenIssuedAt()`).
- The JWT `aud` claim holds the OAuth client id.
  `SymfonyBearerTokenValidator` takes the first `aud` entry as `ATTRIBUTE_OAUTH_CLIENT_ID`.
- An authorization code token carries the approving user's full rights, plus the `write` scope and, for administrators, the `admin` scope (`ScopeRepository::finalizeScopes()`).

On the MCP side:

- `/api/_mcp` (route `api.mcp.endpoint`) is a regular Admin API route.
  `McpAuthenticationListener` accepts `sw-access-key` and `sw-secret-access-key` headers for integration and user access keys and marks the request as pre-authenticated.
  Without those headers the request falls through to the regular bearer validation in `ApiAuthenticationListener`, which accepts password grant, `client_credentials` and authorization code tokens alike.
- `McpExceptionListener` turns a `401` or `403` into a JSON-RPC error with code `-32001` and sends no `WWW-Authenticate` header.
  It also answers `POST /register` with an `invalid_client` JSON error, because Cursor falls back to that path.
- `McpAllowlistProvider` resolves the allowlist per principal.
  Administrators (`user.admin`) are always unrestricted; integrations flagged `admin` are not.
- `McpRateLimiter::enforceForAdminApi()` keys on `ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID`.
  For access-key requests that is the stable `mcp-<accessKey>`; for bearer tokens it is the JWT `jti`, which changes with every new token.
- App tools with a `/api/script/{path}` URL run as Symfony subrequests that inherit the parent request's authentication (`AppMcpCapabilityExecutor`).
- No route answers `/.well-known/oauth-protected-resource` or `/.well-known/oauth-authorization-server` in core.

### Findings from probing a lane

The comment on [#20867](https://github.com/shopware/shopware/issues/20867#issuecomment-6058641213) from 2026-10-08 adds two findings:

- An MCP session is not bound to the principal that opened it.
  An integration token sending an administrator's `Mcp-Session-Id` gets `tools/list` answered from that session.
  The allowlist still applies per request, so nothing escalates, but session state such as enabled toolsets leaks between principals.
- With `SwagAgenticCommerce` installed, `/.well-known/oauth-authorization-server` is answered by the plugin's UCP identity linking route, with a `501` UCP error when identity linking is disabled.

### The SDK narrowed its scope

`mcp/sdk` 0.9 (2026-10-18) implements the resource server role only (its ADR 0002).
It removed the OAuth proxy and the client registration middleware and keeps:

- `ProtectedResourceMetadata` and `ProtectedResourceMetadataHandler`, serving the document at the path derived from the resource (RFC 9728 section 3.1) and requiring https except for `localhost`, `127.0.0.1` and `::1`.
- `AuthorizationMiddleware` with an `AuthorizationTokenValidatorInterface`, answering `401` with `WWW-Authenticate` and `403 insufficient_scope`.
- `ScopePolicy`, mapping scopes to JSON-RPC methods and tools.
- `AccessToken`, readable by handlers through `RequestContext::getAccessToken()`; an application authenticating requests itself may set the attribute instead.

Shopware still runs `mcp/sdk` 0.8.1, because `symfony/mcp-bundle` 0.14.1 pins it.
The upgrade is [#21832](https://github.com/shopware/shopware/issues/21832) and waits for bundle 0.15.

### Scope

In scope: the Admin API endpoint `/api/_mcp`, both protocol eras (authentication does not differ between them).
Out of scope: `/store-api/_mcp`, which authenticates shoppers through the sales-channel context.

## Decision

Shopware stays both authorization server and resource server.
The existing OAuth stack gains discovery and audience-bound tokens, and authentication stays in the Shopware kernel listeners.

### Names and URLs

For a shop whose Admin API is served at `https://shop.example/api`:

| Role | Value |
|---|---|
| Issuer | `https://shop.example/api` |
| Authorization server metadata | `https://shop.example/.well-known/oauth-authorization-server/api` |
| MCP resource (canonical URI) | `https://shop.example/api/_mcp` |
| Protected resource metadata | `https://shop.example/.well-known/oauth-protected-resource/api/_mcp` |

The base URL is the scheme, host and base path of the Admin API request, accepted only when `McpAllowedHostsProvider` allows the host, otherwise `APP_URL`.
For an installation in a subdirectory the base path is part of every URL: issuer `https://shop.example/shop/api`, resource `https://shop.example/shop/api/_mcp`.
The well-known URIs insert their suffix before that path, as RFC 8414 and RFC 9728 define (`https://shop.example/.well-known/oauth-protected-resource/shop/api/_mcp`).

MCP clients may add a query to the endpoint URL, for example `https://shop.example/api/_mcp?toolsets=orders`.
RFC 9728 section 3.1 keeps that query after the well-known path, and section 3.3 requires the client to drop metadata whose `resource` is not identical to the URL it requested.
So the `resource_metadata` URL in the challenge carries the query of the MCP request as sent (`.../.well-known/oauth-protected-resource/api/_mcp?toolsets=orders`), and the metadata answers with that query in `resource` (`https://shop.example/api/_mcp?toolsets=orders`).
Matching a requested resource and the token audience ignore the query (see 2), so one token works with and without toolsets.
The MCP TypeScript SDK, which Claude Code uses, compares only origin and path; strict RFC 9728 clients compare the whole URL, and both succeed.

```
GET /api/_mcp                                    (no token)
401  WWW-Authenticate: Bearer resource_metadata="https://shop.example/.well-known/oauth-protected-resource/api/_mcp"
     {"jsonrpc":"2.0","id":"","error":{"code":-32001,...}}

GET /.well-known/oauth-protected-resource/api/_mcp
200  {"resource":"https://shop.example/api/_mcp","authorization_servers":["https://shop.example/api"]}

GET /.well-known/oauth-authorization-server/api
200  {"issuer":"https://shop.example/api",
      "authorization_endpoint":"https://shop.example/api/oauth/authorize",
      "token_endpoint":"https://shop.example/api/oauth/token",
      "response_types_supported":["code"],
      "grant_types_supported":["authorization_code","refresh_token"],
      "code_challenge_methods_supported":["S256"],
      "token_endpoint_auth_methods_supported":["none"],
      "authorization_response_iss_parameter_supported":true}
```

### 1. Which tokens `/api/_mcp` accepts

Options:

- **A. Everything the Admin API accepts, plus access-key headers** (today).
- **B. Only tokens issued for MCP**, plus integration credentials.
- **C. A, and tokens issued for MCP are accepted nowhere else.**

Decision: **C**.

| Credential | `/api/_mcp` | Rest of the Admin API |
|---|---|---|
| `sw-access-key` and `sw-secret-access-key` headers (integration or user key) | yes | unchanged |
| `client_credentials` bearer token | yes | yes |
| Password grant bearer token (Administration login) | yes | yes |
| Authorization code token without `resource` (e.g. `shopware-cli`) | yes | yes |
| Authorization code token with the MCP resource | yes | **no** |

A token without a resource is an Admin API token, and `/api/_mcp` is part of the Admin API.
Accepting it is a deliberate compatibility exception to the specification's audience rule: OAuth is an additional way to reach `/api/_mcp`, and the existing ways keep working.
The audience rule applies in full to tokens issued with the MCP resource.
Option B would break every setup that uses a copied user token today and gains nothing, because such a token already reaches the whole Admin API.
The risk the specification addresses is the other direction: a token handed to an AI client reaching the rest of the shop.

OAuth is an additional way to reach `/api/_mcp`.
No later major restricts the endpoint to MCP-bound tokens; access keys, integration tokens and user tokens keep working.

### 2. Binding a token to MCP

Options:

- **A. RFC 8707 audience.** The client sends `resource=https://shop.example/api/_mcp` on the authorize and token requests; the token records it.
- **B. An `mcp` scope.** Clients request `scope=mcp`; the token carries it.
- **C. Both.**

Decision: **A, the audience is the binding.**
The specification requires audience validation and requires clients to send `resource`, so A works with every compliant client without scope negotiation.
A scope duplicates the same fact and invites tokens where the two disagree.
Scopes stay free for what they are meant for, a later narrower consent (see 4).

Rules:

- `GET` and `POST /api/oauth/authorize` and `POST /api/oauth/token` accept `resource`.
  The only resource value accepted is the MCP resource of this shop; any other value is answered with `invalid_target` (RFC 8707).
  The token request must name the same resource as the authorization request.
- The resource is compared without its query and without a trailing slash, so `https://shop.example/api/_mcp?toolsets=all` names the same resource.
  Only the discovery answers keep the query, see "Names and URLs".
  A resource with a fragment is rejected, as RFC 8707 requires.
- A token request without `resource` after an authorization request with one still yields a token bound to that resource; RFC 8707 lets the authorization server apply the resource of the grant.
- A refresh keeps the resource of the original grant.
- The JWT keeps the client id as the first `aud` entry and adds the resource as a further entry.
  Readers taking `aud[0]` as client id keep working.
- `ApiAuthenticationListener` rejects a token carrying the MCP resource on every route except `api.mcp.endpoint`, with `401 invalid_token`.
  This includes `POST /api/oauth/authorize`, so an MCP token can never approve another client.
- Subrequests started by an `/api/_mcp` main request (app script tools on `/api/script/{path}`) accept the token only because the MCP request marks them with a request attribute.
  Other subrequests reject it: ESI fragments copy the parent's `Authorization` header, so "is a subrequest" alone would let an MCP token reach any route.
- Clients without `resource` keep getting a plain Admin API token (`shopware-cli`).

### 3. Client registration

Options:

- **A. Static public clients in `shopware.api.oauth_clients`** (today).
- **B. Client ID Metadata Documents.** The client id is an https URL; Shopware fetches the JSON document and takes name and redirect URIs from it.
- **C. Dynamic Client Registration.**

Decision: **A now, B as an opt-in later, never C.**

- A lets Claude Code, Cursor and similar clients with a configurable client id log in as soon as discovery ships.
- B is what web-based clients (claude.ai, ChatGPT) need.
  It fetches a URL named in an unauthenticated request, so it needs SSRF protection, size and time limits, caching and a security review before it ships.
  It reuses the target validation of `adr/2026-07-15-secure-webhook-target-validation.md`, ships disabled by default, and the consent page shows the client id's host next to the self-declared name.
- C is deprecated in the specification and lets anyone create clients on an open endpoint.
  `POST /register` keeps answering `invalid_client`.

Redirect URI rules for static clients stay as they are: loopback IP literals with any port, everything else exact, no wildcards.
Redirect URIs that are neither loopback nor `localhost` must use https; private-use schemes (`cursor://`) are allowed only as an exact match.
Plain http is accepted for `localhost` and the loopback IP literals `127.0.0.1` and `[::1]` only.
The exception is the `dev` kernel environment (`kernel.environment` is `dev`): there every http redirect URI is accepted, so development hosts such as `http://shop.test` are not blocked.
The `test` and `prod` environments enforce the rule.
The check runs on every authorization request, not when the configuration is loaded: `oauth_clients` shipped with 6.7.15.0, and one insecure entry must not stop the whole Admin API from booting.
An insecure redirect URI is answered with `400 FRAMEWORK__OAUTH_INSECURE_REDIRECT_URI` and, like an unregistered one, never receives the error (RFC 6749 section 4.1.2.1).
For CIMD clients the redirect URI must be listed in the fetched document.

Only operators add clients, through configuration in `config/packages`.
There is no Admin API or Administration UI for client registration.

Core ships no entries for third-party MCP clients; `shopware-cli` stays the only default.
The documentation shows how an operator adds a client.

Redirect URIs on the local machine:

- A redirect URI matches only the URIs configured for the client id in the request; loopback any-port matching never applies across clients.
- The port is the only free part, and only for a configured `127.0.0.1` or `[::1]` URI; scheme, host and path match exactly (RFC 8252 section 7.3).
- `http://localhost` is accepted only as an exact match, port included, for a URI the operator configured for that client.
  There is no any-port rule for `localhost`.
  Claude Code calls back on `http://localhost:<port>/callback`, so an operator registers a fixed port, for example `http://localhost:53682/callback`, and the user adds the server with `--callback-port 53682`.
- `localhost` is a name, not an address.
  It can resolve elsewhere through the hosts file or DNS, and a second local process can listen on it for the other address family, so RFC 8252 section 8.3 advises against it; the exact match keeps it limited to a port the operator chose.
- A loopback redirect does not prove which local program asked.
  Any local program can send a public client id; PKCE binds the code to the program that started the flow, and the consent page names the client and the redirect host.

### 4. What the user approves

Options:

- **A. The user's own rights** (today): ACL plus the user's MCP allowlist; administrators bypass the allowlist.
- **B. A and a read-only choice** on the consent page, carried as a scope and enforced next to ACL.
- **C. A tool selection at consent**, stored per token family and intersected with the allowlist.

Decision: **A**, with consent text that says it plainly.
An administrator's approval gives the client unrestricted MCP access, which audience binding limits to `/api/_mcp`.
The consent page names the client, the redirect host and, for MCP tokens, that access is limited to the MCP endpoint.

B follows as its own story after discovery and audience binding.
A read-only grant must not depend on tools honouring a `dryRun` argument, so it is enforced centrally.
A read-only token reaches a tool only when the tool is classified as safe to read:

- its declared privileges are not empty and all of them are `:read` privileges, or
- it is marked as needing no privilege, `#[McpToolRequiresNoPrivilege]` for PHP tools and an explicit equivalent for app tools, defined in that story.

A tool that declares nothing is refused, because an empty privilege list cannot tell a harmless tool from a write tool.
Unannotated plugin tools and app tools without `requiredPrivileges` are such tools today.

C needs storage and UI per token family and is not planned.

### 5. Rate-limit key

Options:

- **A. Token id** (today); a refresh, or a new `client_credentials` token every ten minutes, starts a new bucket.
- **B. Principal:** `user-<userId>` for user-bound tokens, `integration-<accessKey>` for integration tokens and access-key headers, `integration-<accessKey>-user-<userId>` for `sw-app-user-id` requests.
- **C. Principal plus client id.**

Decision: **B**, for every authentication mode.
C lets a user multiply the limit by using several clients, and with CIMD the client id is chosen by the caller.
The client IP stays the fallback when no principal is known.

### 6. Session binding

Decision: **bind handshake-era MCP sessions to the principal** that sent `initialize`, using the same principal value as in 5, never the token id, so a session survives a refresh.
A request whose principal differs from the stored one is answered with the same `404` as an unknown session, so the answer does not reveal that the session exists.
Sessions created before the change carry no principal and are answered with `404`; clients re-initialize.
The stateless era has no sessions and is not affected.

This is independent of OAuth and can ship first as a hardening fix.

### 7. Ownership of `/.well-known/oauth-authorization-server`

Options:

- **A. Core owns the root path** and the plugin moves.
- **B. Core serves the path-suffixed variant** `/.well-known/oauth-authorization-server/api` with issuer `<base>/api`.
- **C. A shared dispatcher** that both register with.

Decision: **B**.
The issuer is the Admin API, so a path issuer is the accurate one, and RFC 8414 path insertion is the first variant MCP clients try.
The root path stays free for `SwagAgenticCommerce` and its shopper identity linking, which is a different authorization server on sales-channel domains.
Core does not serve the root `/.well-known/oauth-protected-resource` either; clients find the path variant through the `WWW-Authenticate` header and through RFC 9728 path derivation.

Consequences for routing:

- With the Storefront installed, `RequestTransformer` resolves a sales channel for every path outside the API prefixes.
  The two path-suffixed prefixes are added to its list of paths that need no sales channel.
- Installations in a subdirectory (`https://shop.example/shop/api`) have their well-known URIs at the host root, outside Shopware.
  They need a web server rewrite, which the documentation describes.

### 8. Access token validity after refresh

Decision: **the previous access token stays valid until it expires**, at most `access_token_ttl` (ten minutes by default).
Rotation and reuse detection apply to refresh tokens.
Revoking a client's access revokes its refresh token family; its last access token runs out within the TTL.
A denylist would add a lookup to every Admin API request for a window of minutes.

MCP-bound tokens use the same `access_token_ttl` of ten minutes; there is no separate setting.

### 9. Reuse of the SDK 0.9 resource-server classes

Options:

- **A. Use `AuthorizationMiddleware`** with a Shopware token validator and a `ScopePolicy` in the transport middleware.
- **B. Keep authentication in `McpAuthenticationListener` and `ApiAuthenticationListener`**, and reuse the SDK value objects.

Decision: **B**.

- Shopware authenticates in kernel listeners before the controller runs.
  Access-key headers, `sw-app-user-id`, the ACL context and the allowlist all depend on that, and the transport middleware runs after it and understands only bearer tokens.
- The audience check also has to reject MCP tokens on every other Admin API route, which a middleware inside the MCP transport never sees.
- `ScopePolicy` authorizes per method and tool by scope; Shopware authorizes tools by ACL and allowlist (`#[McpToolRequires]`).
  Two models for the same decision would drift apart.

What is reused once [#21832](https://github.com/shopware/shopware/issues/21832) lands:

- `ProtectedResourceMetadata` builds the metadata document and its path.
  Its https rule fails for plain-http development hosts other than loopback, so those serve no metadata.
- The transport sets the `AccessToken` request attribute from the validated Shopware token (scopes, `sub`, client id), so SDK-level handlers see the same principal through `RequestContext::getAccessToken()`.

If discovery ships before #21832, a small internal builder serves the same fields and is replaced by the SDK class during the upgrade.
`ScopePolicy` is reconsidered when the read-only consent from 4 is built.

### Error answers

`McpExceptionListener` keeps the JSON-RPC body with `-32001` and adds the `WWW-Authenticate` header to `401` answers on `/api/_mcp`:

- `Bearer resource_metadata="<url>"` when no credentials were sent.
- `Bearer error="invalid_token", resource_metadata="<url>"` for an invalid, expired or wrongly bound token.

No `scope` parameter is sent while Shopware defines no MCP scopes.

## Consequences

### Backwards compatibility

- Integrations with access keys or `client_credentials` tokens are unaffected.
- Copied user tokens and `shopware-cli` tokens keep working on `/api/_mcp` and everywhere else.
- Only tokens issued with the MCP resource are new, and only they are restricted.
- A configured `http` redirect URI on a host other than `localhost` or a loopback IP literal stops working outside the `dev` environment and has to move to https.
- Sessions without a stored principal are rejected once session binding ships; MCP clients re-initialize on `404`.
- The rate-limit bucket of bearer clients changes from token to principal; clients that relied on a fresh bucket per token get throttled sooner.

### Follow-up issues

These replace the proposed sub-issues of [#20866](https://github.com/shopware/shopware/issues/20866):

1. **Session binding** to the principal, `404` on mismatch (6).
   Can ship first.
2. **Rate-limit key** on the principal for every authentication mode (5).
3. **Discovery:** protected resource metadata, authorization server metadata at the path-suffixed URI, `WWW-Authenticate` on `401`, `iss` in the authorization response (RFC 9207), `RequestTransformer` exemption, documentation for subdirectory installs (7, error answers).
4. **Audience-bound tokens:** `resource` on authorize and token requests, `aud` entry, rejection outside `/api/_mcp` for main requests, refresh keeps the resource (2).
5. **Consent text** for MCP clients, including the administrator notice and the MCP-only notice (4).
6. **Client ID Metadata Documents** behind a flag, with SSRF review (3).
7. **`shopware-mcp-evals`:** an OAuth login path next to integration keys.
8. **Read-only consent** on the consent page, enforced centrally by the privileges a tool declares (4).

### For extension developers

- Apps and plugins calling the Admin API with a token obtained for MCP get `401`.
  They use their own integration credentials as before.
- Plugins that answer `/.well-known/oauth-authorization-server` at the root keep that path.
