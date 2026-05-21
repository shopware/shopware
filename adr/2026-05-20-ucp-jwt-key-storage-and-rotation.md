---
title: UCP JWT key storage, signing, and rotation
date: 2026-05-20
area: framework
tags: [framework, ucp, jwt, cryptography, security, key-rotation, rfc-9421]
---

## Context

UCP requires every business and every platform to publish a JSON Web Key (JWK)
Set in their `/.well-known/ucp` profile. These keys are used for:

- **HTTP Message Signatures (RFC 9421)** on requests and webhooks
- **JWT-encoded mandates** in the optional AP2 extension
- **OAuth 2.0 access-token signing** in the Identity Linking capability

The UCP specification mandates **asymmetric** ECDSA signatures (`ES256` required,
`ES384` optional). Shopware today uses **HMAC-SHA256** with `APP_SECRET` for
Admin-API OAuth tokens
(`src/Core/Framework/Api/OAuth/JWTConfigurationFactory.php`). That mechanism is
symmetric and cannot be exposed to third-party platforms — exposing the symmetric
secret would allow any holder to forge admin tokens.

We need to decide:

1. Whether to reuse the existing `Lcobucci\JWT` toolchain or pull in a separate
   library.
2. Where to store the new asymmetric private keys.
3. How key rotation works without breaking active platform integrations.
4. How keys are bound to a Sales Channel.

## Decision

### Reuse the Lcobucci JWT stack

We reuse the `Lcobucci\JWT` library and its `Configuration` / `Builder` /
`Parser` primitives — the same primitives that power
`JWTConfigurationFactory`, `JWTGenerator`, and `JWTDecoder`. We do **not** pull
in a second JWT library.

A new factory `UcpJwtConfigurationFactory` produces an asymmetric
`Configuration::forAsymmetricSigner(new Sha256() /* ES256 */, $privateKey,
$publicKey)` per Sales Channel. A new abstract generator
`UcpJwtGenerator extends JWTGenerator` reuses the existing template-method
pattern, specialising it for UCP webhook JWTs and AP2-mandate JWTs.

RFC 9421 HTTP Message Signatures sit on top of this: `Rfc9421SignatureBuilder`
and `Rfc9421SignatureVerifier` consume a `Signer` and `Key` resolved from the
same per-Sales-Channel JWT configuration. We therefore have **one key pipeline**
serving both JWT and RFC 9421 signing needs.

### Key storage

Private keys are stored **in the database** in a new entity
`ucp_signing_key`, scoped to a Sales Channel, encrypted at rest:

| Column                       | Purpose                                                |
| :--------------------------- | :----------------------------------------------------- |
| `id`                         | Binary UUID, primary key                               |
| `sales_channel_id`           | Foreign key to `sales_channel`, cascading delete       |
| `kid`                        | RFC 7517 key identifier, unique per Sales Channel      |
| `algorithm`                  | `ES256` (default) or `ES384`                           |
| `public_jwk`                 | JSON Web Key, public part, included in `/.well-known/ucp` |
| `private_key_pem_encrypted`  | PEM-encoded private key, AES-256-GCM encrypted         |
| `status`                     | `active`, `retiring`, `retired`                        |
| `activated_at`, `retiring_at`| Timestamps for rotation accounting                     |

The encryption key for `private_key_pem_encrypted` is derived from `APP_SECRET`
via HKDF-SHA256 with a fixed salt `ucp/signing-key-v1` and the per-row `kid` as
context, ensuring distinct ciphertext per key even when re-encrypted.

We do **not** store private keys on the filesystem (as is conventional for
OAuth2 server private keys) for three reasons:

1. **Multi-channel separation** — each Sales Channel has its own key set; a
   filesystem-per-channel layout would complicate deployments.
2. **Container-friendly** — DB storage works identically across local dev,
   Kubernetes, and traditional hosting without extra volume mounts.
3. **Rotation atomicity** — DAL transactions guarantee rotation is atomic with
   the new key becoming active and the old one transitioning to `retiring`.

### Rotation procedure

Rotation is a three-state finite-state machine:

```
       create new                 24h grace
[absent] --------> [active] --------------> [retiring] --24h--> [retired] -----> [deleted]
                       |                       ^
                       |  rotate               |
                       +-----------------------+
```

- At any time exactly one key per Sales Channel is `active`.
- During rotation the previous `active` key transitions to `retiring`. Both
  `active` and `retiring` keys are published in the JWKS exposed via the
  profile. Outbound signatures use the `active` key only; inbound verification
  accepts either.
- A scheduled task `UcpKeyRetirementTask` (daily) transitions keys older than
  24 hours from `retiring` to `retired`. Retired keys remain in the database
  for audit purposes but are removed from the published JWKS.
- A separate `UcpKeyHardDeleteTask` (weekly) permanently deletes keys that have
  been retired for at least 30 days.

The 24-hour grace window matches the UCP profile-cache TTL recommendation: any
platform that cached our profile shortly before rotation will still successfully
verify signatures emitted with the previous key.

### Operations exposed

The following operations are exposed:

- Admin API:
  - `POST   /api/_admin/ucp/sales-channels/{id}/keys` — create a new key (auto-rotates if one is active)
  - `POST   /api/_admin/ucp/sales-channels/{id}/keys/{kid}/retire` — manual retirement
  - `DELETE /api/_admin/ucp/sales-channels/{id}/keys/{kid}` — permanent delete (only when retired ≥ 24h)
- CLI:
  - `bin/console ucp:keys:list --sales-channel=…`
  - `bin/console ucp:keys:create --sales-channel=…`
  - `bin/console ucp:keys:rotate --sales-channel=…`
  - `bin/console ucp:keys:retire --sales-channel=… --kid=…`
- Privilege: All key-mutating operations require the dedicated
  `ucp.key_rotator` ACL privilege, separate from `ucp.editor`.

### Key material guardrails

A `KeyMaterialGuard` Monolog processor strips any field matching the patterns
`private_key`, `private_jwk`, or `pem_encrypted` from log contexts globally for
the `ucp` channel. This catches accidental `dump()` / `error_log()` calls in
extensions.

A custom PHPStan rule `UcpPrivateKeyLeakRule` rejects calls to logging methods
where the variable name includes `private_key` or comes from
`UcpSigningKey::getPrivateKey*()`.

## Consequences

### Positive

- Reusing Lcobucci avoids a second JWT library and a second cryptographic
  primitive set for plugin authors to learn.
- DB-backed key storage scales identically across all Shopware hosting
  topologies; no extra deployment artifacts.
- The 24-hour grace window allows zero-downtime rotation, which is required
  for any production-grade integration with external platforms.
- The dedicated `ucp.key_rotator` privilege creates a clear blast-radius around
  rotation operations.

### Negative

- Encrypting private keys with a value derived from `APP_SECRET` ties UCP key
  rotation to `APP_SECRET` rotation. If the application secret is rotated,
  all UCP private keys become unreadable and must be re-encrypted in a
  migration step. We mitigate this with a dedicated CLI command
  `ucp:keys:reencrypt --old-secret=… --new-secret=…`.
- DB storage of secrets violates the strict "secrets never touch the DB"
  principle. We accept this trade-off because (a) the keys are encrypted
  with a per-installation secret, (b) database backups already contain
  comparable secrets (e.g. encrypted customer payment-method references),
  and (c) filesystem storage would not improve security in container-native
  deployments where filesystem secrets are typically synced from the same
  secret manager.
- Lcobucci does not implement RFC 9421 directly; we layer our own
  `Rfc9421SignatureBuilder` on top of its primitives. This is additional
  code we own and must maintain.

### Neutral

- We do not support hardware-backed key storage (HSM/KMS) in PR #1. The
  storage layer is interface-driven (`UcpSigningKeyRepositoryInterface`), so a
  KMS-backed implementation can be added later without breaking changes.
- We do not support `EdDSA` (Ed25519) in PR #1. The UCP spec marks it
  optional; adoption in the wild is currently negligible for `ES256`.
