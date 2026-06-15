---
title: Signed Administration ACL token for app backends
date: 2026-06-15
area: administration
tags: [app, administration, meteor-extension-sdk, acl, jwt]
---

## Context

Apps can render Administration modules through their app base URL. Those modules can already ask the Meteor Admin SDK for frontend user information through `sw.context.getUserInformation()`. That is useful for rendering UI, but it is not an authorization mechanism for the app backend. Anything the iframe sends to the app backend can be changed by the browser.

Shopware can enforce app and user permissions when the request goes back to the Shopware Admin API. This is covered by the `sw-app-integration-id` and `sw-app-user-id` model, where the effective permissions are the intersection of the app permissions and the current Administration user's permissions. That does not help an app backend that handles its own requests and cannot call the Shopware instance. Local shops, private-network shops, and cloud-hosted app backends are common examples where the browser can reach both systems, but the app backend cannot reach the shop.

We need a signed, short-lived permission snapshot for the current Administration session. The app backend must be able to verify that snapshot locally with data it already has from app registration.

## Decision

Add a Meteor Admin SDK context method that returns a short-lived signed token for the current Administration user and the calling app:

```ts
const { token, expiresAt } = await sw.context.getAclToken();
```

The initial API does not accept a permission filter. The token always contains the effective permissions for the current app and current Administration user:

```text
effectivePermissions = appPermissions intersect currentUserPermissions
```

For an admin user, `currentUserPermissions` is treated as unrestricted, so the token still contains only permissions granted to the app. The app permission boundary is never bypassed.

Use the existing raw Shopware ACL privilege names, for example `product:read`, `order:update`, `sales_channel:read`, `api_send_email`, or app-defined additional privileges. Do not use Administration UI role aliases such as `product.viewer` or `order.editor` as the token contract.

The token can also be requested with an app integration access token for backend-to-backend use cases, but only when the request includes `sw-app-user-id`. The authenticated integration id must belong to the requested app, and the referenced user must be allowed to access that app through `app.all` or `app.<appName>`. Without a resolved user id the request is rejected, so the token always represents an Administration user and does not need a separate integration-only claim shape.

The JWT payload should contain at least:

```json
{
  "iss": "shop-id",
  "aud": "app:SwagExample",
  "sub": "admin-user-id",
  "iat": 1781510400,
  "nbf": 1781510400,
  "exp": 1781510700,
  "jti": "random-token-id",
  "shopId": "shop-id",
  "shopUrl": "https://shop.example",
  "appName": "SwagExample",
  "appVersion": "1.2.3",
  "integrationId": "app-integration-id",
  "permissions": ["order:read", "sales_channel:read"]
}
```

Sign the token with the existing app secret and a symmetric JWT algorithm. The app backend verifies the signature, issuer, audience, expiry, app identity, and permission claim shape locally before checking the permissions required by its own backend action.

Use a short TTL, for example 5 minutes. This keeps permission changes and disabled users from staying valid for long without adding server-side token state.

## App usage

An app frontend asks the Meteor Admin SDK for a token and forwards it to its own backend:

```ts
const { token } = await sw.context.getAclToken();

await fetch('https://app.example/admin/analytics', {
    method: 'POST',
    headers: {
        Authorization: `Bearer ${token}`,
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({ range: '30d' }),
});
```

The app backend verifies the token with the app secret and checks the permissions required by its own action:

```ts
const claims = await verifyAdministrationAclToken(token, {
    appSecret,
    shopId,
    appName: 'SwagExample',
});

if (!claims.permissions.includes('sales_channel:read')) {
    return new Response(null, { status: 403 });
}
```

When an app backend requests the token through its app integration, it must send `sw-app-user-id`. The token still represents that Administration user and is used by the app backend in the same way.

## Consequences

App backends can authorize requests for Administration iframe modules without trusting frontend-provided permission data and without calling back into the Shopware instance.

Frontend-only checks such as `sw.context.getUserInformation()` remain useful for UI rendering, but documentation must state that they are insufficient for backend authorization.

Server-side app SDKs should provide verification helpers for this token contract. The Meteor Admin SDK only exposes token creation because verification requires the app secret and belongs on the app backend.

JWTs have no protocol-level size limit, but HTTP headers often have practical limits around 8 KB to 16 KB. The token includes the full effective permission intersection because that is the simplest and least surprising behavior.

Token revocation is time-based. A permission change or deactivated user can remain valid until the short token TTL expires. Do not add server-side token storage unless that becomes a measured requirement.

Do not deliver this token as an iframe query parameter. It can become too large and query parameters are more likely to be logged. App backends should receive it as an `Authorization` header on their own requests.
