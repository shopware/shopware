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

The facade remains the extension boundary. `createHTTPClient()` returns the Axios instance extended with the Shopware additions that are part of the contract (`isCancel` and, until 6.8, the deprecated `CancelToken` shim). Shopware's `HttpClient`, `HttpRequestConfig`, `HttpResponse` and `HttpError` types describe the supported surface; Axios types are not part of it. `HttpClient` stays structurally compatible with `AxiosInstance` so `axios-mock-adapter` keeps working without casts.

`AbortController` is the only supported cancellation mechanism. `httpClient.isCancel()` recognises both `AbortController` cancellations and errors produced by the deprecated `CancelToken`.

`useAxiosV1` keeps working, but it selects a compatibility mode instead of a transport.
A differential comparison of the two shipped clients against a real server found two differences the mode can restore.
Axios 0.x kept `[` and `]` literal in query strings, and it returned response headers as a plain object rather than an `AxiosHeaders` instance.
The mode restores the query encoding through Axios' own `paramsSerializer.encode` hook, for requests and for `httpClient.getUri()`, and converts the response headers.
The resolution order stays the same: an explicit `useAxiosV1` wins, otherwise the `V6_8_0_0` feature flag decides.

One difference cannot be restored.
Axios 1.x merges the `common` and method header groups before the request interceptors run, so interceptors see one flat `AxiosHeaders` instance.
An interceptor that writes into `config.headers.common` fails, with or without the mode.
The release notes and the upgrade guide state this.

All legacy runtime code lives in one module, `http-legacy-compat.ts`, registered by a single call in the HTTP factory.
Removing it deletes that file, the call and the `CancelToken` export in the factory, and the deprecated type declarations.
All of them are removed with 6.8 and guarded with `Shopware.Feature.triggerDeprecationOrThrow('V6_8_0_0', …)`: the `useAxiosV1` request option, `httpClient.CancelToken` and the `cancelToken` request option, and `axiosV0`, `axiosV1`, `interceptorsV0`, `interceptorsV1`, `defaultsV0` and `defaultsV1`, which all alias the single client, its interceptors and its defaults.
The opt-out therefore covers the rest of 6.7; from 6.8 on every request behaves like Axios 1.x.

The Administration does not migrate to `fetch` or `ofetch` now. Upload progress needs XHR, which Axios selects in the browser, and the facade obligations (`.data`, `.status`, `.headers`, `error.response.*`, `responseType`, `timeout`, `signal`, interceptors) cost the same regardless of the engine. If the engine is ever swapped, it happens behind this facade.

## Consequences

Extensions keep using `httpClient`, and on 6.7 the documented opt-out stays available for query encoding and response headers.
Extensions whose request interceptors write into the header groups have to set the header on `config.headers` directly, already on 6.7. Code that used `CancelToken` or read the version-specific escape hatches also keeps working and receives a development-mode deprecation warning that names the replacement. The `UPGRADE-6.8.md` entry and the migration guide describe the required changes.

The client bundle carries one Axios copy instead of two, and the factory loses the adapter layer, the `Proxy` mirroring and the dual cache interceptor. Tests exercise one transport; `axios-mock-adapter` attaches to the Axios instance directly.

The compatibility mode restores the two differences it can, not the legacy implementation. An extension that depended on an Axios 0.x internal the comparison did not surface has to migrate; `httpClient.isCancel()` and the documented response and error properties are the supported surface. Because the mode is a small, enumerable set of interceptors rather than a second dependency, it can be carried until 6.8 at negligible cost.

A later transport change stays possible, but is a separate decision with its own ADR.
