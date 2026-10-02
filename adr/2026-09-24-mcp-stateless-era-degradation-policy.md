---
title: MCP behaviour on the stateless 2026-07-28 protocol era
date: 2026-09-24
area: framework
tags: [framework, mcp, ai, protocol]
---

## Context

### Two protocol eras

The MCP specification changed fundamentally with its 2026-07-28 revision. Clients and SDKs call the revisions before and after that change "protocol eras". This ADR names the two eras by how they behave:

**Handshake era** (revisions up to 2025-11-25). This is what Shopware serves today.

1. The client opens a connection by sending `initialize`.
2. The server answers and returns a session id in the `Mcp-Session-Id` response header.
3. The client confirms with a `notifications/initialized` message.
4. Every following request carries the session id, so the server can keep state per connection. Examples are which toolsets the client has enabled and which clients to notify about changes.
5. When the client is done, it sends an HTTP `DELETE` and the server throws the session state away.

```mermaid
sequenceDiagram
    participant C as MCP client
    participant S as Shopware MCP server
    C->>S: initialize
    S-->>C: capabilities, Mcp-Session-Id header
    C->>S: notifications/initialized
    Note over S: state kept per Mcp-Session-Id:<br/>enabled toolsets, sessions to notify,<br/>stored large results
    C->>S: tools/list, tools/call, resources/read (Mcp-Session-Id)
    S-->>C: results from the request plus the session state
    S-->>C: notifications/tools/list_changed pushed to the session
    C->>S: DELETE (Mcp-Session-Id)
    S-->>C: 204, session state discarded
```

**Stateless era** (revision 2026-07-28 and later).

- There is no `initialize` and no session id. A client that wants to know what the server supports calls `server/discover`.
- Instead, every request carries everything the server needs to know about the client in its `_meta` field: the protocol version, the client capabilities and, for example, the log level the client wants for this request.
- The client never sends `DELETE`. Disconnecting is purely local on the client side. A server that still receives a `DELETE` answers `405`.
- Every request can be handled by a fresh server instance. Nothing a request writes into per-connection memory is visible to the next request.
- Notifications that the server wants to push (for example "the tool list changed") no longer go to a session. The client opens a stream with `subscriptions/listen` and the server publishes to it.

```mermaid
sequenceDiagram
    participant C as MCP client
    participant S as Shopware MCP server
    Note over S: no session, nothing survives<br/>from one request to the next
    C->>S: server/discover
    S-->>C: capabilities
    C->>S: tools/list, tools/call (_meta: protocol version, client capabilities, log level)
    S-->>C: results derived from the request only<br/>(URL ?toolsets=, credentials, ACL)
    C->>S: subscriptions/listen (opens a stream)
    S-->>C: tools/list_changed published to the stream
    Note over C: disconnect is local, no DELETE is sent
    C->>S: DELETE from a client that still sends one
    S-->>C: 405
```

The `mcp/sdk` 0.8 used by Shopware serves both eras from the same URL. The client decides which one it speaks, and the server answers in the same era. Shopware currently switches the stateless era off for both endpoints (`/api/_mcp` and `/store-api/_mcp`) with `Builder::withoutModernEra()` in `McpServerBuilderCompilerPass` (the SDK and the MCP Inspector call the stateless era "modern"). As soon as that switch is removed, every client on the stateless era gets the stateless behaviour described above.

### What in Shopware depends on the session

Three features, plus cleanup and validation code, rely on the handshake-era session id:

| Feature | How it works today | What happens on the stateless era |
|---|---|---|
| Enabling a toolset during a conversation | `shopware-toolset-enable` stores the enabled toolsets in `mcp_toolset_session`, keyed by session id. The following `tools/list` reads them back. | The tool reports success, but the next request has no session id, so the enabled tools never show up. |
| "Tool list changed" notifications | `McpListChangedNotifier` and `McpSessionRegistry` remember the open sessions and push a notification to each one when tools change (for example after an app install). | There are no sessions to push to, so no client is notified. |
| Large tool results | Results above 100 KB are stored in `mcp_tool_result_cache` under the session id. The response only contains a pointer (`shopware://tool-result/<id>`). The model reads the full result with `resources/read`, and `ToolResultResource` only serves it to the same session. | Without a session id, `McpToolResponse` falls back to returning the complete result inline, which can be several megabytes. Even a stored result couldn't be read, because the next request belongs to a different "session". |
| Cleanup on `DELETE` | `McpSessionCleanupSubscriber` removes the session's cached results and toolset rows. | The client never sends `DELETE`, so nothing is cleaned up. |
| Session id validation | `McpSessionIdValidator` rejects malformed `Mcp-Session-Id` headers. | Not relevant, since there is no header. |

None of these fail loudly. The client looks connected and gets answers, but it sees the wrong tools, misses updates, or receives huge inline payloads. On top of that, the Admin and Store endpoints could each solve this differently, which would give extension developers and client authors two mental models for one product.

## Decision

### One rule for both eras and both endpoints

What a request can see and do is derived from the request itself: the URL (for example `?toolsets=order,media`), the credentials, and the ACL and allowlist of the authenticated principal. It is never derived from state that the server keeps between requests.

The rule applies to the Admin endpoint and the Store endpoint in the same way. A feature that needs state across requests either finds a stateless design or is explicitly limited to the handshake era.

### Per feature

**Choosing tools.** The supported way to pick toolsets is the URL: `/api/_mcp?toolsets=order,media` or `?toolsets=all`. It already works on both eras and with every client, because the client only has to call the URL it was configured with. `shopware-toolset-enable` keeps working for handshake clients. On the stateless era it must not pretend to succeed. It is either not offered there at all, or it answers with a clear error that tells the client to reconnect with `?toolsets=`.

**Change notifications.** Notifications are a convenience, not something correctness depends on, because `?toolsets=` doesn't need them. Handshake clients keep the current notifications. For clients on the stateless era we use the SDK's `subscriptions/listen` support, backed by a cache pool that all PHP workers share. We don't build our own session store just to keep notifications alive.

**Large tool results.** The pointer to a stored result no longer depends on the session. Instead it contains a short-lived, signed token that identifies the stored result and the principal (integration, user or sales-channel context) that produced it. Any later request by the same principal can read it, on both eras. We deliberately don't use the OAuth token id (`jti`) for this. The pointer ends up in model context and logs, so if it leaks it must be limited in time and verifiable on its own, not equal to an identifier of the caller's credentials. The pointer is returned as an MCP `resource_link` content block, the native way to reference a resource, not as a Shopware-specific `_meta.resourceUri` field. Stored results are removed by age through a scheduled task, not by `DELETE`.

Today only the Admin server can read stored results. Store tools already store large results, but the Store server has no resource to read them back. The Store endpoint therefore gets its own tool-result resource, authorized by the same signed token, as part of this change.

**Cleanup and validation.** `DELETE` cleanup and `Mcp-Session-Id` validation stay as they are for handshake clients. Nothing on the stateless era may depend on them.

**Multi-step requests.** On the stateless era a tool can ask the client for more input and continue in a follow-up request. The SDK keeps the intermediate state in a signed `request_state` value, which needs a secret shared by all workers. That secret must be configured before we offer any feature built on it (for example URL-based confirmation for destructive tools). Those features add to the existing `dryRun` default, they don't replace it.

### Rollout

Both endpoints will serve both eras from the same URL. We don't switch to stateless-only, because most clients in the field still speak the handshake era. The stateless era is switched on only once the points above are implemented and covered by tests that run the same scenarios against both eras.

## Consequences

**For MCP clients and users.** Handshake clients keep working exactly as today. `?toolsets=` becomes the recommended setup for everyone, because it works on both eras. Clients on the stateless era never get a toolset enable that silently does nothing.

**For extension developers.** Tools, prompts and resources don't need to know which era a request uses. The model is the same for the Admin and the Store endpoint. Extensions must not keep their own state keyed by `Mcp-Session-Id`, because it disappears on the stateless era. State that must survive between requests has to be keyed by the principal or passed in the request.

**For operators.** Stored large results are cleaned up by age, whether or not a client ends its session. Shared state for notifications and multi-step requests needs a cache pool and a secret that all workers share, for example Redis in multi-server setups.

**For Shopware development.** The result cache is re-keyed from the session to the signed token, the Store endpoint gets a result resource, and `shopware-toolset-enable` is hidden or returns an error on the stateless era. Tests have to run the same scenarios against both eras and check the behaviour described here, not whatever the code happens to do. When the implementation lands, `src/Core/Framework/Mcp/AGENTS.md` and `docs/spec-coverage.md` are updated. A future removal of the handshake era is out of scope here.

Related issues: epic #19965, #20512 (this decision), #19966 (result pointer), #20511 (cleanup by age), #19969 (serving the stateless era).
