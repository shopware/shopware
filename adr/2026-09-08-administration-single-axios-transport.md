---
title: One Axios transport behind the Administration HTTP client facade
date: 2026-09-08
area: administration
tags: [administration, axios, deprecation, extensions]
status: accepted
---

## Context

[Keep Administration HTTP transports behind a compatibility facade](_superseded/2026-07-23-administration-http-client-compatibility-facade.md) decided to run Axios 0.x and Axios 1.x side by side behind a Shopware-owned facade, so extensions could migrate at their own pace. Direct HTTP requests selected a transport with `useAxiosV1`, repository requests always used 1.x, and the facade mirrored interceptors and defaults onto both clients.

The incremental path worked. Repository calls moved to Axios 1.x without a single source change in an extension, and Axios 1.x became the default for direct requests once the `V6_8_0_0` feature flag is active. No requirement to stay on Axios 0.x has come up since.

Coexistence has a standing price:

- Twelve npm audit advisories stay suppressed because they only affect the Axios 0.x line: proxy bypasses, proxy authorization leaks, prototype pollution gadgets, form serializer denial of service and a ReDoS. They are listed with that reason in the Administration's `scripts/runNpmAudit.ts`.
- `httpClient.interceptors` and `httpClient.defaults` are hand-written proxies that clone every handler and mirror every property write into both clients, rather than the Axios ones.
- The request methods (`get`, `post`, `postForm`, `getUri` and the rest) are re-implemented on a dispatcher function, because the facade is not an Axios instance.

The superseded ADR left the end of the transition open. It promised an announcement through the release information and the applicable major upgrade guide, without naming a release, so extension authors had no date to plan against.

## Decision

Axios 1.x is the only HTTP transport of the Administration. The second transport and the transitional compatibility surface are removed with Shopware 6.8, announced as deprecated with `@deprecated tag:v6.8.0` in 6.7.

The durable half of the superseded decision is unchanged:

- The Administration exposes a Shopware-owned HTTP client facade. The concrete Axios instance stays private and is not part of the public contract.
- `HttpClient`, `HttpRequestConfig` and `HttpResponse` are the contract extensions depend on.
- Axios is not part of the repository contract. A repository incompatibility is fixed centrally in Shopware rather than worked around by each extension.

The transitional half ends:

- The Axios 0.x dependency is removed. Axios 1.x is installed as `axios` instead of behind the `axios-v1` alias.
- The `useAxiosV1` request flag and the version dispatcher are removed.
- The mirrored interceptor manager and mirrored defaults are removed. `httpClient.interceptors` and `httpClient.defaults` forward to a single private Axios 1.x instance instead of being mirrored onto two.
- Shopware does not modify the Axios instance. The facade stays a distinct object that delegates to it, and members the `HttpClient` contract declares but an instance does not carry — `isCancel` and `CancelToken`, which `axios.create()` leaves off — live on the facade and are imported by name from Axios.
- The version adapter module and the six escape hatches are removed.
- Structural `AxiosInstance` compatibility stops being a goal.

## Consequences

Extensions that never selected a transport need no change. An extension pinned to `useAxiosV1: false` must migrate before 6.8; afterwards the flag is an unknown configuration key that Axios ignores, so the opt-out fails silently rather than throwing. This is why the removal is announced a minor ahead and the flag carries a deprecation annotation.

The facade shrinks to a thin delegate. Interceptors and defaults registered through it are the Axios ones, so a handler is registered once instead of cloned into two stacks, and behaviour no longer depends on which transport served a request. Because the instance stays unmodified, an Axios upgrade changes what the facade delegates to rather than a set of Shopware-owned patches on it.

The twelve suppressed advisories are resolved by the removal rather than by an exception in the audit configuration.

The superseded ADR's boundary is preserved; its compatibility guarantee is not. It promised to normalize cancellation, errors, configuration and test compatibility in one place. The first three still hold. The fourth ends with structural `AxiosInstance` compatibility: extensions that hold the client in an Axios-typed variable, or attach `axios-mock-adapter` to it, have to change. So do Shopware's own specs, which mock through the escape hatches this removal deletes. The replacement for that mocking path is decided with the removal.

## References

- Superseded ADR: `adr/_superseded/2026-07-23-administration-http-client-compatibility-facade.md`
- [The Administration HTTP client](../src/Administration/Resources/app/administration/technical-docs/09-security/axios-migration-guide.md)
- `UPGRADE-6.8.md`, section "Axios 1.x is the only HTTP client of the Administration"
