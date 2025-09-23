---
title: Improved APP URL verification
date: 2025-09-22
area: framework
tags: [app-system, administration]
---

## Context

Recent improvements in Shopware, including the introduction of the fingerprinting mechanism for the Shop ID (see PR https://github.com/shopware/shopware/pull/11677), have made it possible to detect changes in the Shopware environment that should trigger a Shop ID change. Examples include when the APP_URL changes or the environment otherwise looks different, such as when a production shop is copied to a staging system.

Detecting these changes allows us to cut off communication with app servers until the Shop ID has been updated. Amongst other issues, this prevents two different shops from communicating as the same shop to an app server.

However, risks remain:

* Different shops might use the same APP_URL.
* A shop might be configured with an incorrect APP_URL.

Some APP URL verification already exists in the Shopware Administration, but we want to improve its robustness.

## Decision

When a Shop ID changes (see scenarios below): we verify that the APP_URL points back to the same instance of Shopware (itself).

We achieve this by introducing a new public API end point `api/app-system/shop/verify`.

Verification flow:

1. Shopware calls this endpoint on the instance defined by the configured APP_URL.
2. The request includes a random token and a cache key stored in a short-lived cache.
3. The endpoint loads the cached value using the key and verifies the token matches.
4. If successful, we can assume with high probability that the APP_URL points to the correct instance.

Shop ID changes include: 
 * Migration from v1 to v2 structure (on shop id load).
 * APP_URL changed (when no apps installed).
 * The installation path of Shopware changed (when no apps installed).
 * New Shop ID generated (where no ID existed before).
 * After app communication was cut off due to fingerprint mismatches and the user manually resolved using one of the strategies.


### Potential issues:
 * No cache is configured.
 * Multiple instances use the same cache.

### Open questions:
 * Previously, validation was limited to production environments: should we keep that restriction?
 * Previously, validation could be disabled, should we still allow that?

## Consequences
 * APP_URL is verified when the Shop ID changes.
 * App communication is cut off if the URL validation fails.
 * The user is prompted to resolve the issue in the admin (this feature already exists)
