---
title: Secure webhook target validation
date: 2026-07-15
area: framework
tags: [webhook, security, ssrf, guzzle, validation]
---

## Context

Webhook delivery sends HTTP requests from the Shopware server to URLs configured by administrators or apps. The delivery result is persisted in `webhook_event_log`, including the response status and response content. This makes webhook targets security-sensitive: if an attacker can configure an internal URL, the webhook worker becomes a server-side request forgery read primitive.

The existing webhook delivery path uses `shopware.webhook.guzzle` through `WebhookClient`. A webhook target is currently accepted as a plain string. Without target validation, direct metadata, loopback, private, link-local, or reserved destinations can be requested from the webhook worker's network.

Guzzle follows redirects by default. Validating only the initially configured URL is therefore insufficient because a public URL can redirect to a private, loopback, link-local, metadata, or otherwise reserved target.

Disabling redirects would close that bypass, but it would also break legitimate webhook receivers that use redirects for endpoint migration, canonical host routing, or infrastructure-level forwarding.

## Decision

Webhook delivery validates every outbound destination before the HTTP request is sent. This includes the initially configured webhook URL and every redirect target.

The validation model is:

- Validate `webhook.url` when it is written through the DAL so invalid targets fail early. This is an operator feedback mechanism, not the security boundary.
- Validate the URL again immediately before delivery so old rows and changed DNS records are checked at the actual network boundary. Delivery-time validation is mandatory and decides whether a request may be sent.
- Enforce HTTPS-only targets by default. Operators may explicitly allow unencrypted webhook traffic through `shopware.yaml`, with the default set to disallow HTTP.
- Require a syntactically valid URL with an existing host.
- Reject direct IP-literal hosts by default. Operators may explicitly allow required internal IP literals through the private IP allow-list in `shopware.yaml`.
- Use a shared IP validation helper for webhook and media upload-by-URL host validation so IPv4, IPv6, mapped-address, private-range, reserved-range, and DNS resolution behavior stays consistent.
- Resolve both A and AAAA records for the host. Every resolved address must be public unless it matches the configured internal IP allow-list. If any record resolves to a private, loopback, link-local, reserved, or otherwise non-public IP range that is not allow-listed, the target is invalid.
- Provide an operator-controlled `shopware.yaml` configuration for webhook network policy. The exact configuration names are decided during implementation, but the defaults must disallow unencrypted traffic and internal network targets.
- Disable Guzzle's implicit redirect handling for webhook requests instead of relying on defaults.
- Validate each redirect target before the redirected request is sent. The redirect handling must preserve the DNS result used during validation for the actual redirected request as well.
- Restrict redirect protocols to the same scheme policy as the initial target. If unencrypted webhook traffic is disabled, redirects are HTTPS-only as well.
- Preserve the webhook request method during redirects by issuing redirected webhook requests explicitly.
- Keep a bounded redirect limit.
- If the active Guzzle handler supports cURL options, pin each validated host and port to the resolved public IP used during validation with `CURLOPT_RESOLVE` for the corresponding request. This reduces DNS rebinding risk between validation and connection. The request must keep TLS peer and host verification enabled.

Webhook delivery sets `allow_redirects` to `false` and follows redirects in an explicit loop. This lets Shopware validate and pin every redirect target before the next request is sent.

When cURL options are available, the delivery request options include a resolve pin for the exact hostname and effective port:

```php
'curl' => [
    CURLOPT_RESOLVE => [
        sprintf('%s:%d:%s', $host, $port, $validatedPublicIp),
    ],
]
```

The webhook URL validator must evaluate the complete target URL, not only the host. It must enforce the allowed scheme and port policy, apply the configured internal IP allow-list, resolve both A and AAAA records, and reject any record resolving to private, loopback, link-local, reserved, or otherwise non-public IP ranges that are not explicitly allow-listed.

Delivery-time validation remains mandatory even with write-time validation. Webhook rows may predate the validation change, and DNS can change after a webhook was saved.

## Considered Approaches

### Disable redirects

This is the simplest SSRF hardening measure and removes redirect-based bypasses entirely. It was not chosen because existing valid webhook endpoints may depend on redirects. Breaking those endpoints would turn a security fix into an avoidable compatibility issue.

### Follow redirects manually

Shopware could disable Guzzle redirects, inspect `Location` headers, validate the resolved URI, and issue the next request itself. This gives full control over each hop, but duplicates redirect semantics already implemented by Guzzle and increases implementation complexity around relative locations, status-specific method handling, limits, and response propagation.

### Trust post-request redirect history

Guzzle can track redirect history with `track_redirects`. This is not a security control because the redirected request has already been sent by the time the response headers are available. It can be useful for diagnostics but cannot prevent SSRF.

### Allow unrestricted direct IP targets after IP range validation

Direct IP webhooks could be allowed if the IP itself passes range validation. This was not chosen as the default because IP literal parsing has many edge cases across IPv4, IPv6, mapped addresses, octal/decimal/hex notation, and bracketed URI forms. Requiring DNS names by default keeps the webhook contract simpler and aligns with public HTTPS certificate validation, while the operator allow-list keeps private app backends possible.

## Consequences

### Positive

- Legitimate webhook redirects continue to work.
- Direct webhook requests to metadata, loopback, private, link-local, reserved, or malformed targets are blocked at write time and delivery time unless explicitly allow-listed by the operator.
- Redirects to metadata, loopback, private, link-local, or reserved targets are blocked before the redirected request is sent unless explicitly allow-listed by the operator.
- The same validation policy applies to newly written webhooks, existing webhook rows, and redirect targets.
- HTTPS-only delivery is the secure default and prevents clear-text webhook payload delivery and redirect downgrades unless unencrypted traffic is explicitly enabled by the operator.
- cURL resolve pinning reduces DNS rebinding exposure when the active Guzzle handler supports it.
- Invalid destinations stay inside the existing webhook failure handling path.
- Explicit redirects avoid Guzzle's browser-like `POST` to `GET` rewrite on `301`, `302`, and `303` responses.

### Negative / trade-offs

- Redirect delivery becomes dependent on the correctness of the URL validator and explicit redirect loop.
- Existing webhooks using HTTP, direct IP targets, reserved development hostnames, or redirects from HTTPS to HTTP require explicit operator configuration.
- Endpoints that intentionally redirect from public hosts to private network hosts require explicit operator configuration.
- DNS can still change between validation and connection if cURL resolve pinning is unavailable for the active Guzzle handler.
- Pinning one resolved IP can reduce CDN/load-balancer flexibility for a single delivery attempt.
- Each redirect hop performs validation and DNS resolution, adding a small amount of latency to redirected deliveries.
- Media upload-by-URL uses a different transport and is not hardened by this webhook-specific change.

### Operational impact

Webhook operators should configure final public HTTPS endpoints with DNS hostnames where possible. Redirects remain supported, but every hop must satisfy the same target requirements as the original webhook URL. HTTP endpoints and internal network targets are available only through explicit `shopware.yaml` configuration and remain disabled by default.
