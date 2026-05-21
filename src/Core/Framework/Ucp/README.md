# Universal Commerce Protocol (UCP) for Shopware

This folder contains Shopware's server-side implementation of the Universal Commerce Protocol (UCP), an open standard for agentic commerce. A UCP-aware platform such as ChatGPT, Gemini, Perplexity or a custom buyer agent can discover a Shopware sales channel, negotiate supported commerce capabilities, search products, create carts, complete checkouts, receive signed order webhooks and optionally link a buyer identity through OAuth 2.0.

The implementation is experimental and gated by the `UCP_SERVER` feature flag.

## Current Status

The implementation has been tested live against a Dockware Shopware 6.7.2.2 container and the local `simulator/` platform agent. The primary flows verified in the current workspace are:

| Flow | Result |
|---|---|
| `GET /.well-known/ucp` discovery | Pass, HTTP 200 |
| UCP profile services | Pass, advertises `rest`, `mcp`, `a2a`, `embedded` |
| UCP profile keys | Pass, publishes active EC signing key in `signing_keys` |
| OAuth metadata at `/.well-known/oauth-authorization-server` | Pass, HTTP 200 |
| REST cart create | Pass, returns UCP cart with totals and `expires_at` |
| REST idempotency replay | Pass, repeated request returns `Idempotency-Replay: 1` |
| REST idempotency conflict | Pass, same key + different body returns HTTP 409 |
| REST checkout create | Pass, returns `available_instruments`, `payment_handlers`, `fulfillment`, `continue_url` |
| REST checkout complete | Pass, creates real Shopware order |
| MCP cart lifecycle via simulator | Pass |
| A2A `message/send` structured catalog search | Pass |
| Tokenization endpoint | Pass for negative case: invoice handler returns `handler_declined` |
| AP2 mandate scenario | Pass for server-side order placement; simulator's merchant-authorization verifier still needs exact detached-JWS/JCS parity |
| Signed order webhook | Pass, Shopware log shows POST to simulator webhook with HTTP 200 |

The simulator is intentionally stricter in some places and intentionally simplified in others. It is useful for end-to-end testing, but the official UCP conformance suite should remain the certification gate.

## Quick Start

Enable the feature flag:

```bash
bin/console feature:enable UCP_SERVER
```

Configure a sales channel through the UCP Admin module or by creating a row in `ucp_sales_channel_config`. A minimal local test setup uses:

```sql
UPDATE ucp_sales_channel_config
SET
  active = 1,
  signature_policy = 'log',
  idempotency_required = 0,
  enabled_transports = JSON_ARRAY('rest', 'mcp', 'a2a', 'embedded'),
  enabled_capabilities = JSON_ARRAY(
    'dev.ucp.shopping.catalog.search',
    'dev.ucp.shopping.catalog.lookup',
    'dev.ucp.shopping.cart',
    'dev.ucp.shopping.checkout',
    'dev.ucp.shopping.order',
    'dev.ucp.common.identity_linking',
    'dev.ucp.shopping.discount',
    'dev.ucp.shopping.fulfillment',
    'dev.ucp.shopping.loyalty',
    'dev.ucp.shopping.buyer_consent'
  );
```

### Production configuration

Production deployments **must** run with strict inbound signature verification and required idempotency keys. The migration default for `signature_policy` is already `strict`; the snippet below makes the production posture explicit and is the form to copy into deployment automation:

```sql
UPDATE ucp_sales_channel_config
SET
  active = 1,
  signature_policy = 'strict',
  idempotency_required = 1,
  enabled_transports = JSON_ARRAY('rest', 'mcp', 'a2a', 'embedded'),
  enabled_capabilities = JSON_ARRAY(
    'dev.ucp.shopping.catalog.search',
    'dev.ucp.shopping.catalog.lookup',
    'dev.ucp.shopping.cart',
    'dev.ucp.shopping.checkout',
    'dev.ucp.shopping.order',
    'dev.ucp.common.identity_linking',
    'dev.ucp.shopping.discount',
    'dev.ucp.shopping.fulfillment',
    'dev.ucp.shopping.loyalty',
    'dev.ucp.shopping.buyer_consent'
  );
```

Smoke-test for `strict`:

1. A signed request with a valid `Signature` and `Signature-Input` header that resolves to an active key in the platform's profile must succeed.
2. The same request with one byte mutated in the body (so the `content-digest` no longer matches) must be rejected with HTTP 401 and a log line carrying the offending `kid`.

The local simulator runs unsigned and is therefore intentionally configured with `signature_policy = 'log'`.

Verify discovery:

```bash
curl https://shop.example/.well-known/ucp
```

Verify OAuth metadata:

```bash
curl https://shop.example/.well-known/oauth-authorization-server
```

Run the local simulator:

```bash
cd ../../../../../simulator
npm install
npm run dev
```

Then open `http://localhost:4100`, set `Business URL` to `http://localhost:8080`, select a scenario and run it.

## Architecture

```text
Platform agent
  |
  | GET /.well-known/ucp
  v
Discovery
  WellKnownUcpController
  UcpProfileBuilder
  SupportedVersionsRegistry
  UcpSigningKeyProvider
  CapabilityRegistry
  UcpPaymentHandlerRegistry
  |
  | UCP-Agent + optional RFC 9421 signature
  v
Request ingress
  UcpAgentRequestResolver
    - sales-channel-domain resolution
    - platform profile fetch
    - capability negotiation
    - inbound signature verification
    - idempotency claim/replay
    - embedded-session validation
    - bearer-token validation
  |
  v
Capability controllers / tools
  CatalogController
  CartController
  CheckoutController
  OrderController
  DiscountController
  OAuth controllers
  MCP tools
  A2A controller
  Embedded controller
  Tokenization controller
  |
  v
Shopware Store API + DAL
```

## Folder Structure

| Path | Purpose |
|---|---|
| `Api/` | Admin API for UCP configuration, signing-key operations and profile preview |
| `Capability/` | UCP capabilities and extensions |
| `Command/` | Console commands for debugging and key management |
| `DataAbstractionLayer/` | UCP entity definitions and entities |
| `Discovery/` | Well-known profile, profile builder, sales-channel-domain resolution |
| `Event/` | Public UCP extension events |
| `Idempotency/` | Idempotency-Key two-phase claim/replay store |
| `Jwt/` | EC key generation, JWK/PEM helpers, JCS canonicalization |
| `Negotiation/` | Platform/business capability intersection |
| `Payment/` | Payment-handler registry, descriptors, tokenization integration |
| `Profile/` | Platform profile fetching, validation, URL safety and discovery budget |
| `ScheduledTask/` | Key retirement and cache/session cleanup |
| `Security/` | Private-key encryption and log redaction |
| `Transport/Rest/` | REST ingress, response envelope, exception mapping |
| `Transport/Signature/` | RFC 9421 HTTP Message Signatures and RFC 9530 Content-Digest |
| `Transport/Mcp/` | MCP JSON-RPC endpoint and UCP tools |
| `Transport/A2A/` | A2A Agent Card and JSON-RPC message routing |
| `Transport/Embedded/` | Embedded Protocol iframe bridge and session model |
| `Transport/Tokenization/` | Payment credential tokenization endpoint |

## Discovery and Sales Channel Binding

UCP profiles are sales-channel-domain specific. The request host maps to a `sales_channel_domain`, and that domain's sales channel loads one `ucp_sales_channel_config` row.

Main endpoint:

```http
GET /.well-known/ucp
```

The profile contains:

| Field | Meaning |
|---|---|
| `ucp.version` | Current UCP version for the sales channel |
| `ucp.services` | Transports (`rest`, `mcp`, `a2a`, `embedded`) |
| `ucp.capabilities` | Enabled capabilities and extensions |
| `ucp.payment_handlers` | Business-offered payment handlers |
| `ucp.supported_versions` | Version-pinned profile links |
| `signing_keys` | Active and retiring EC public keys as JWK |

The profile is built by `UcpProfileBuilder`; plugins can modify it through `UcpEvents::PROFILE_BUILT`.

## Capability Coverage

| Capability | Identifier | Status | Main Implementation |
|---|---|---|---|
| Catalog Search | `dev.ucp.shopping.catalog.search` | Implemented | `Capability/Catalog/CatalogController.php`, `SearchCatalogTool.php` |
| Catalog Lookup | `dev.ucp.shopping.catalog.lookup` | Implemented | `CatalogController.php`, `LookupProductsTool.php`, `GetProductTool.php` |
| Cart | `dev.ucp.shopping.cart` | Implemented | `CartController.php`, cart MCP tools |
| Checkout | `dev.ucp.shopping.checkout` | Implemented | `CheckoutController.php`, checkout MCP tools |
| Order | `dev.ucp.shopping.order` | Implemented | `OrderController.php`, `OrderWebhookPublisher.php`, `GetOrderTool.php` |
| Identity Linking | `dev.ucp.common.identity_linking` | Implemented | OAuth controllers and repositories |
| Discount | `dev.ucp.shopping.discount` | Implemented | `DiscountMapper.php`, `DiscountController.php` |
| Fulfillment | `dev.ucp.shopping.fulfillment` | Implemented | `FulfillmentMapper.php` |
| Buyer Consent | `dev.ucp.shopping.buyer_consent` | Implemented | `BuyerConsentMapper.php`, `ConsentStore.php` |
| Loyalty | `dev.ucp.shopping.loyalty` | Extension point | `LoyaltyAggregator.php`, provider tag `ucp.loyalty_provider` |
| AP2 Mandates | `dev.ucp.shopping.ap2_mandate` | Plugin | `custom/plugins/SwagUcpAp2Mandates` |

## Transports

### REST

REST endpoints live below `/ucp/v1`.

| Endpoint | Purpose |
|---|---|
| `POST /ucp/v1/catalog/search` | Catalog search |
| `POST /ucp/v1/catalog/lookup` | Catalog lookup |
| `POST /ucp/v1/carts` | Create cart |
| `GET /ucp/v1/carts/{id}` | Read cart |
| `PUT/PATCH /ucp/v1/carts/{id}` | Update cart |
| `POST /ucp/v1/carts/{id}/cancel` | Cancel cart |
| `POST /ucp/v1/carts/{id}/discounts` | Apply discount code |
| `POST /ucp/v1/checkout-sessions` | Create checkout |
| `GET /ucp/v1/checkout-sessions/{id}` | Read checkout |
| `PUT/PATCH /ucp/v1/checkout-sessions/{id}` | Update checkout |
| `POST /ucp/v1/checkout-sessions/{id}/complete` | Complete checkout |
| `POST /ucp/v1/checkout-sessions/{id}/cancel` | Cancel checkout |
| `GET /ucp/v1/orders/{id}` | Read order |
| `POST /ucp/v1/tokenize` | Payment tokenization |

Every UCP response is wrapped by `UcpResponseEnvelopeListener`. The envelope is operation-filtered so a cart route only advertises cart-related capabilities, while checkout routes include checkout-related capabilities and `ucp.payment_handlers`.

### MCP

The MCP endpoint is:

```http
POST /ucp/mcp
```

Supported methods:

| MCP method | Purpose |
|---|---|
| `initialize` | MCP handshake |
| `tools/list` | List negotiated tools |
| `tools/call` | Invoke a UCP tool |
| `ping` | Liveness |
| `notifications/initialized` | No-op ack |

The MCP transport accepts the platform profile in either:

```http
UCP-Agent: profile="https://platform.example/profile.json"
```

or the JSON-RPC envelope metadata:

```json
{
  "params": {
    "_meta": {
      "ucp-agent": {
        "profile": "https://platform.example/profile.json"
      }
    }
  }
}
```

The metadata profile URI is URL-safety validated before it is mirrored into the normal ingress pipeline.

### A2A

Agent Card:

```http
GET /.well-known/agent-card.json
```

Runtime endpoint:

```http
POST /ucp/a2a
```

Supported JSON-RPC methods:

| Method | Purpose |
|---|---|
| `message/send` | Synchronous A2A turn |
| `tasks/get` | Fetch task state |
| `tasks/cancel` | Cancel task |

`A2AMessageTranslator` routes structured `DataPart` actions to existing MCP tools. For example, `search_catalog` maps to the MCP `search_catalog` tool, and `add_to_cart` maps to `update_cart`.

### Embedded Protocol

Embedded URLs:

```http
GET /ucp/embedded/cart/{cartId}?origin=https://host.example
GET /ucp/embedded/checkout/{cartId}?origin=https://host.example
```

The embedded page uses `window.postMessage` and JSON-RPC 2.0 methods (`ep.cart.*`, `ec.*`) to communicate with the host.

Security rules:

| Rule | Implementation |
|---|---|
| `origin` query parameter required | `EmbeddedController::resolveHostOrigin()` |
| No wildcard parent | No `*` fallback in postMessage |
| CSP locked to origin | `frame-ancestors 'self' <origin>` |
| Short-lived embedded session | `EmbeddedSessionFactory` |
| REST bridge authentication | `X-UCP-Embedded-Session` verified by `UcpAgentRequestResolver` |

## Security Model

### HTTP Message Signatures

Inbound and outbound signatures use:

| Spec | Implementation |
|---|---|
| RFC 9421 HTTP Message Signatures | `Rfc9421SignatureBuilder`, `Rfc9421SignatureVerifier` |
| RFC 9530 Content-Digest | `ContentDigestCalculator` |
| ES256 required | `EcKeyGenerator`, OpenSSL ECDSA |
| ES384 optional | supported |
| JWK profile keys | `signing_keys` in `/.well-known/ucp` |
| Replay protection | `SignatureReplayGuard`, table `ucp_signature_nonce` |
| Platform allowlist | enforced during profile negotiation/fetching |
| Webhook URL safety | order webhook targets pass URL safety validation before dispatch |

Inbound signature policy is per sales channel:

| Policy | Behavior |
|---|---|
| `strict` | Signature failure rejects request |
| `log` | Signature failure is logged; request continues, but security-sensitive signals are not trusted |
| `off` | Verification disabled |

Production should use `strict`.

### Profile Fetching and SSRF Protection

Platform profile fetching is handled by `PlatformProfileFetcher` and `UrlSafetyValidator`.

Protections:

| Threat | Mitigation |
|---|---|
| Private IP SSRF | private, loopback, link-local and reserved ranges rejected outside dev |
| Cloud metadata access | metadata hosts/IPs blocked even in dev |
| Redirect attacks | redirects disabled |
| DNS rebinding | resolved IP is validated and pinned with `CURLOPT_RESOLVE` |
| Userinfo spoofing | URLs with `user:pass@host` rejected |
| Oversized responses | max 256 KiB |
| Stale profile fallback | only from previously validated cache |

### Idempotency

Non-idempotent routes use `Idempotency-Key`.

Implementation:

| Step | Behavior |
|---|---|
| First request | `IdempotencyStore::claim()` inserts pending row atomically |
| Concurrent retry | returns 409 while first request is in-flight |
| Same key + same body | replays cached response with `Idempotency-Replay: 1` |
| Same key + different body | returns HTTP 409 |
| Retention | 48 hours |

The fingerprint includes route name, method, path, sorted query string and raw body.

### OAuth 2.0 Identity Linking

Discovery:

```http
GET /.well-known/oauth-authorization-server
GET /ucp/v1/.well-known/oauth-authorization-server
```

Supported flows:

| Feature | Status |
|---|---|
| Authorization Code | implemented |
| PKCE S256 | required |
| Refresh Token | implemented |
| ES256 access-token JWTs | implemented |
| `client_secret_post` | implemented by League |
| `client_secret_basic` | advertised; supported by League where configured |
| `private_key_jwt` | implemented by `ClientAuthenticator` |
| `tls_client_auth` | implemented by `ClientAuthenticator` |
| Consent CSRF protection | HMAC consent ticket cookie + hidden CSRF token |

Bearer-token validation is fail-closed: if an `Authorization: Bearer` header is present but invalid, the request is rejected instead of falling through as anonymous.

### Guest Checkout

UCP permits anonymous agent flows. Shopware's `CartOrderRoute` requires a customer, so `GuestCustomerProvisioner` creates a guest customer at complete-checkout time when no customer is attached to the context.

The guest customer:

| Field | Behavior |
|---|---|
| email | buyer email when supplied; synthetic UCP guest email otherwise |
| address | buyer billing address when supplied; conservative placeholder otherwise |
| account type | guest |

### AP2 Mandates Plugin

The AP2 plugin lives at:

```text
custom/plugins/SwagUcpAp2Mandates
```

It adds:

| Feature | Implementation |
|---|---|
| AP2 capability injection | `Ap2CheckoutSubscriber::onProfileBuilt()` |
| Checkout mandate verification | `MandateVerifier::verifyMandates()` |
| Payment mandate verification | Reads from `ap2.payment_mandate` or payment credential token |
| Issuer pinning | mandate `iss` must equal negotiated platform profile URI |
| Expiry/TTL checks | `exp` required, max TTL enforced |
| Algorithm binding | JWS header `alg` must match JWKS `alg` |
| Replay protection | atomic `swag_ucp_ap2_mandate_log` insert by mandate id |
| Intent binding | amount, currency, line items and merchant host checked |
| Merchant authorization | detached JWS in `ap2.merchant_authorization` |
| Canonicalization | RFC 8785/JCS via `JsonCanonicalization` |
| SD-JWT VC + Key-Binding | parsed and verified per IETF `oauth-sd-jwt-vc` + RFC 7800 — `_sd[]` hash matching against `_sd_alg=sha-256` disclosures, KB-JWT bound via `sd_hash` and verified against `cnf.jwk`, audience pinning, `iat` skew + TTL bounds |

The plugin accepts both wire formats:

- **SD-JWT VC + Key-Binding** (`<issuer-jwt>~<disclosure>~…~<kb-jwt>`) — the production form. Routed by `MandateVerifier::verifyJws()` whenever the mandate string contains `~`.
- **Compact JWS** (`header.payload.signature`) — accepted for simulator/test scenarios. Same claim and signature checks as the SD-JWT issuer-JWT path, but no key-binding requirement.

## Payment Handling and Tokenization

Payment handlers implement `UcpPaymentHandlerInterface` and are registered through the `ucp.payment_handler` tag.

Responsibilities:

| Method | Purpose |
|---|---|
| `describe()` | Adds handler metadata to profile and checkout response |
| `prepareInstrument()` | Converts platform credential to Shopware payment method + token |
| `supportsTokenisation()` | Signals whether raw credential tokenization is supported |
| `tokenize()` | Optional PSP-side tokenization |

The default invoice handler does not tokenize and returns `handler_declined` for `/ucp/v1/tokenize`.

## Database Tables

| Table | Purpose |
|---|---|
| `ucp_sales_channel_config` | UCP activation/config per sales channel |
| `ucp_signing_key` | EC signing keys, encrypted private key material |
| `ucp_platform_profile_cache` | fetched platform profile cache |
| `ucp_negotiation_session` | negotiated platform sessions, used for webhooks |
| `ucp_oauth_client` | OAuth clients |
| `ucp_oauth_auth_code` | OAuth auth codes |
| `ucp_oauth_access_token` | OAuth access token registry |
| `ucp_oauth_refresh_token` | OAuth refresh tokens |
| `ucp_oauth_client_assertion` | `private_key_jwt` jti replay cache |
| `ucp_idempotency_key` | Idempotency replay store |
| `ucp_signature_nonce` | RFC 9421 signature replay store |
| `ucp_buyer_consent` | buyer consent snapshots |
| `ucp_a2a_task` | A2A task/message replay store |
| `ucp_embedded_session` | Embedded Protocol session tokens |
| `swag_ucp_ap2_mandate_log` | AP2 mandate audit/replay table |

## Extension Points

### Capabilities

Register a capability:

```xml
<service id="Vendor\Plugin\Capability\MyCapability">
    <tag name="ucp.capability" capability="com.vendor.shopping.my_capability"/>
</service>
```

### Payment Handlers

```xml
<service id="Vendor\Plugin\Payment\MyUcpHandler">
    <tag name="ucp.payment_handler" handler_id="com.vendor.payments"/>
</service>
```

### Loyalty Providers

```xml
<service id="Vendor\Plugin\Loyalty\MyProvider">
    <tag name="ucp.loyalty_provider"/>
</service>
```

### Events

| Event | Purpose |
|---|---|
| `UcpEvents::PROFILE_BUILT` | mutate the published profile |
| `UcpEvents::CHECKOUT_REQUEST` | validate checkout request before side effects |
| `UcpEvents::CHECKOUT_RESPONSE` | append response-side extensions |
| `UcpEvents::ORDER_WEBHOOK_DISPATCHED` | observe webhook dispatch |

## Live Test Commands

### Discovery

```bash
curl -s http://localhost:8080/.well-known/ucp | jq .
```

### OAuth Metadata

```bash
curl -s http://localhost:8080/.well-known/oauth-authorization-server | jq .
```

### REST Cart and Checkout

```bash
PROFILE='profile="http://host.docker.internal:4100/profile.json"'
PRODUCT_ID='11dc680240b04f469ccba354cbf0b967'

curl -s -X POST http://localhost:8080/ucp/v1/carts \
  -H 'Content-Type: application/json' \
  -H "UCP-Agent: $PROFILE" \
  -H 'Idempotency-Key: demo-1' \
  -d "{\"line_items\":[{\"item\":{\"id\":\"$PRODUCT_ID\"},\"quantity\":1}]}"
```

### Simulator Happy Path

```bash
curl -s -X POST http://localhost:4100/api/run \
  -H 'Content-Type: application/json' \
  -d '{"businessUrl":"http://localhost:8080","transport":"rest","scenarioId":"checkout.happy-path"}'
```

### Simulator MCP Cart Lifecycle

```bash
curl -s -X POST http://localhost:4100/api/run \
  -H 'Content-Type: application/json' \
  -d '{"businessUrl":"http://localhost:8080","transport":"mcp","scenarioId":"cart.full-lifecycle","params":{"cancel":false}}'
```

### Simulator AP2

```bash
curl -s -X POST http://localhost:4100/api/run \
  -H 'Content-Type: application/json' \
  -d '{"businessUrl":"http://localhost:8080","transport":"rest","scenarioId":"extension.ap2-mandate"}'
```

AP2 must be enabled in `enabled_capabilities` for that scenario.

## Security Tests

Targeted unit tests were added under `tests/unit/Core/Framework/Ucp`:

| Test | Security guarantee |
|---|---|
| `SignalsExtractorTest` | unsigned requests cannot inject trusted signals |
| `AttributionExtractorTest` | spec GA-style attribution fields are normalized |
| `IdempotencyStoreTest` | route name participates in idempotency fingerprint; stale claims are re-claimed atomically |
| `A2AMessageTranslatorTest` | A2A add/remove actions map to existing tools |
| `Rfc9421SecurityTest` | duplicate `kid` rejected; replay guard invoked |
| `ClientAuthenticatorSecurityTest` | `private_key_jwt` requires `jti` |
| `EmbeddedControllerSecurityTest` | no wildcard embedded origin |
| `JsonCanonicalizationTest` | JCS key sorting/escaping is deterministic |

Local verification was run in the Dockware container with the standalone PHPUnit runner:

```bash
docker exec shopware-ucp /tmp/phpunit-runner/vendor/bin/phpunit \
  --bootstrap /tmp/phpunit-runner/bootstrap.php \
  --colors=never \
  /var/www/html/tests/unit/Core/Framework/Ucp \
  /var/www/html/custom/plugins/SwagUcpAp2Mandates/tests
```

Result: `OK (104 tests, 165 assertions)`.

## Known Gaps and Notes

The implementation is now broad and exercises every UCP surface in live tests, but several areas should still be treated carefully before certification:

| Area | Note |
|---|---|
| AP2 SD-JWT+KB | Implemented. Both SD-JWT VC + Key-Binding (production form) and compact JWS (simulator/test form) are accepted; `_sd_alg=sha-256` is enforced and KB-JWT is bound via `sd_hash` + `cnf.jwk`. |
| Catalog pagination | Implemented as opaque cursor pagination via `CursorCodec` (HMAC-signed, query-fingerprint-bound, 15 min TTL). Legacy `limit`/`offset` requests still accepted for one release. |
| Catalog product detail | Implemented as REST `POST /catalog/product` and MCP `get_product`; MCP batch lookup is exposed as spec `lookup_catalog`. |
| Discount request shape | Cart/checkout create/update now accept the spec `discounts.codes[]` object and responses emit `discounts.applied[]`; the dedicated `POST /carts/{id}/discounts` route remains as a convenience shortcut. |
| OAuth metadata | Discovery advertises only implemented token endpoint auth methods and no longer publishes unimplemented introspection/revocation endpoints. |
| Strict inbound signatures | Local simulator runs with `signature_policy='log'` because simulator REST calls are unsigned. Production should use `strict`. |
| Simulator matrix | REST and MCP were both verified through every simulator scenario: 34/34 runs passed against the Dockware shop. |
| Full official conformance | Shopware ships a non-production simulation-signature adapter plus `ucp:conformance:seed` for the `flower_shop` fixture so the upstream Python suite at `Universal-Commerce-Protocol/conformance` can be run against a UCP-enabled shop. Local full suite result: `TOTAL=13 FAILURES=0`. A wired CI workflow is intentionally deferred to a follow-up PR. |

## Related Documents

- `AGENTS.md` — internal architecture notes and extension conventions
- `../../adr/2026-05-20-ucp-feature-flag-and-bundle-placement.md`
- `../../adr/2026-05-20-ucp-jwt-key-storage-and-rotation.md`
- `../../adr/2026-05-20-ucp-sales-channel-binding.md`
- `custom/plugins/SwagUcpAp2Mandates/README.md`
- `simulator/README.md`
