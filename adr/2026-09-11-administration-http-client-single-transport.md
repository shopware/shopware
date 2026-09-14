---
title: Administration HTTP client runs on a single Axios 1.x transport
date: 2026-09-11
area: administration
tags: [administration, axios, http-client, extensions]
---

## Context

[Keep Administration HTTP transports behind a compatibility facade](2026-07-23-administration-http-client-compatibility-facade.md) introduced a Shopware-owned HTTP client facade so the Administration could move from Axios 0.x to Axios 1.x without breaking extensions. During that transition the facade dispatched each request to one of two Axios instances, selected by the `useAxiosV1` request option and the `V6_8_0_0` feature flag, and mirrored interceptors and defaults into both instances through `Proxy` objects.

The transition state turned out to be the expensive part. Two Axios copies shipped in the bundle, the interceptor and defaults mirroring was the most fragile code in the factory, cancellation had two idioms (`CancelToken` and `AbortController`), and every test that touched the client had to reason about which transport it exercised. Meanwhile the core's own use of the contested Axios surfaces was small: two upload-progress sites, eight blob downloads, one global timeout, and interceptors registered in a single file.

A proposal to replace Axios with `fetch` or `ofetch` was raised at the same time. Its prerequisites, one transport, one cancellation idiom and one test seam, are exactly the work needed to finish the Axios migration.

## Decision

The Administration HTTP client is a single Axios 1.x instance. Axios 0.x, the `axios-v1` package alias, the per-request transport selection and the mirrored interceptor and defaults machinery are removed. The Administration follows the current Axios 1.x release line.

The facade remains the extension boundary. `createHTTPClient()` returns the Axios instance extended with the Shopware additions that are part of the contract (`isCancel` and, for one major, the deprecated `CancelToken` shim). Shopware's `HttpClient`, `HttpRequestConfig`, `HttpResponse` and `HttpError` types describe the supported surface; Axios types are not part of it. `HttpClient` stays structurally compatible with `AxiosInstance` so `axios-mock-adapter` keeps working without casts.

`AbortController` is the only supported cancellation mechanism. `httpClient.isCancel()` recognises both `AbortController` cancellations and errors produced by the deprecated `CancelToken`.

Compatibility members are kept for one major and warn on first use: the `useAxiosV1` request option is accepted as a no-op, `httpClient.CancelToken` and the `cancelToken` request option keep working, and `axiosV1`, `interceptorsV1` and `defaultsV1` stay as aliases of the client, its interceptors and its defaults. The `axiosV0`, `interceptorsV0` and `defaultsV0` escape hatches are removed because there is no second instance for them to expose.

The Administration does not migrate to `fetch` or `ofetch` now. Upload progress needs XHR, which Axios selects in the browser, and the facade obligations (`.data`, `.status`, `.headers`, `error.response.*`, `responseType`, `timeout`, `signal`, interceptors) cost the same regardless of the engine. If the engine is ever swapped, it happens behind this facade.

## Consequences

Extensions keep using `httpClient` unchanged. Code that set `useAxiosV1`, used `CancelToken` or read the version-specific escape hatches keeps working until the next major and receives a development-mode deprecation warning that names the replacement. The `UPGRADE-6.8.md` entry and the migration guide describe the required changes.

The client bundle carries one Axios copy instead of two, and the factory loses the adapter layer, the `Proxy` mirroring and the dual cache interceptor. Tests exercise one transport; `axios-mock-adapter` attaches to the Axios instance directly.

Behaviour that only the legacy transport provided, such as the `__CANCEL__`-marked `Cancel` objects or Axios 0.x error shapes, is gone. Extensions that depended on it must move to `httpClient.isCancel()` and the documented response and error properties.

A later transport change stays possible, but is a separate decision with its own ADR.
