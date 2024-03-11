---
title: API version removal
date: 2020-12-02
area: core
tags: [api, versioning, deprecation]
---

## Context

Due to the new deprecation strategy and the 6-8 months major cycle, API versioning is no longer  reasonable or even possible.
Deprecated fields and routes are currently tagged in a minor version and will be removed with the next major version.
The API version is currently not increased in a minor version, which would not make sense, because with every second minor version deprecations would have to be removed.

## Decision

By removing the API versioning within the URL we want to simplify usage and the deprecation strategy of our API. 
The deprecated fields and routes are shown in the OpenAPI scheme as well as the API changelog and will be removed with the next major version (`6.x`).

## Consequences

All route URLs are changed from `/api/v{VERSION}/product` to `/api/product`. 
Beginning with 6.3.5.0 both route URLs are accessible via `/api/v{VERSION}/product` and `/api/product` before and until the release of 6.4.0.0 in order to enable preparation of connections well in advance.
