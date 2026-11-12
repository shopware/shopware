# Administration HTTP client: Axios 1.x

## Overview

The Shopware Administration HTTP client (`httpClient`) is a single Axios 1.x instance behind a Shopware-owned facade. The legacy Axios 0.x dependency, the `axios-v1` package alias and the dual-transport machinery have been removed. The `useAxiosV1` opt-out survives the removal as a compatibility mode on the single client, so nothing is forced to migrate at once.

Extension code continues to use the injected `httpClient`. Axios stays an implementation detail: the facade, not Axios, is the extension boundary, and Shopware's HTTP types describe the supported contract.

## What changed

| Before | Now |
| --- | --- |
| Two Axios copies (`axios` 0.x and `axios-v1`) | One `axios` 1.x dependency |
| `useAxiosV1` selected the transport per request | `useAxiosV1` selects the legacy compatibility mode per request and is deprecated |
| `CancelToken` cancelled Axios 0.x requests | `AbortController` cancels requests; `CancelToken` is deprecated |
| `axiosV0`, `interceptorsV0`, `defaultsV0` escape hatches | Deprecated aliases of `httpClient`, `httpClient.interceptors`, `httpClient.defaults` |
| `axiosV1`, `interceptorsV1`, `defaultsV1` escape hatches | Deprecated aliases of `httpClient`, `httpClient.interceptors`, `httpClient.defaults` |

All deprecated members stay functional until the next major and log a deprecation warning in development mode the first time they are used.

## The opt-out still works

Removing the second Axios copy does not force you to migrate. `useAxiosV1: false` still restores the behaviour of the legacy transport, it is just implemented as a compatibility mode on the single Axios 1.x client instead of a second dependency.

The defaults are unchanged:

| Context | Default behaviour | Override |
| --- | --- | --- |
| Shopware 6.7 | Legacy compatibility mode | `useAxiosV1: true` |
| Shopware 6.8 (`V6_8_0_0` active) | Axios 1.x | `useAxiosV1: false` |
| Repository requests | Follows the same defaults; the option is not part of the repository contract | None |

Two behaviours differ between the legacy transport and plain Axios 1.x, and the compatibility mode restores both:

1. **Query encoding.** The legacy transport decoded `[`, `]`, `:`, `$` and `,` back to their literal form, so `{ ids: [1, 2] }` became `?ids[]=1&ids[]=2`. Axios 1.x leaves them percent-encoded: `?ids%5B%5D=1&ids%5B%5D=2`. Both are equivalent for a correct server, but signature checks, log filters and strict route matchers can depend on the literal form.
2. **Response headers.** The legacy transport returned `response.headers` as a plain object. Axios 1.x returns an `AxiosHeaders` instance. Lower-case property access such as `response.headers['content-type']` works with both.

Everything else, including the error class and its codes, `FormData` handling, JSON parsing and `CancelToken`, is already identical in both. If your extension breaks on something outside this list, it depends on an Axios internal that was never part of the contract; please report it.

An explicit `paramsSerializer` in the request configuration always wins over the compatibility mode.

## Migrating direct HTTP requests

Remove the `useAxiosV1` option once the request works without it:

```javascript
// Before
this.httpClient.get('/api/endpoint', { useAxiosV1: true });

// Now
this.httpClient.get('/api/endpoint');
```

If a request does break, set `useAxiosV1: false` on it as a temporary measure and migrate later. Avoid spreading the override across an extension: a widespread opt-out hides migration problems and makes the eventual removal harder.

API services extending `ApiService` need no further change.

## Repository requests

No change is required:

```javascript
const product = await this.productRepository.get(productId, context);
```

Repositories never exposed the transport as part of their contract, so `useAxiosV1` is not available on repository calls. If a repository operation behaves differently, treat it as a Shopware compatibility issue rather than working around it.

## Request cancellation

Replace `CancelToken` with `AbortController`:

```javascript
// Before (deprecated)
const source = httpClient.CancelToken.source();
httpClient.get('/api/endpoint', { cancelToken: source.token });
source.cancel('Operation cancelled');

// Now
const controller = new AbortController();
httpClient.get('/api/endpoint', { signal: controller.signal });
controller.abort();
```

Detect cancellations with the facade, not with the error class:

```javascript
if (httpClient.isCancel(error)) {
    // request was cancelled
}
```

`httpClient.isCancel()` recognises `AbortController` cancellations and errors produced by the deprecated `CancelToken`.

## Errors

The common response properties remain available:

```javascript
error.response.status
error.response.data
error.response.headers
```

Do not depend on the exact error class or message. Use `httpClient.isCancel(error)` for cancellations.

## Interceptors and defaults

Register customizations through the facade:

```javascript
const interceptorId = httpClient.interceptors.request.use(myRequestHandler);
httpClient.defaults.headers.common['my-header'] = 'value';

httpClient.interceptors.request.eject(interceptorId);
```

`httpClient.interceptors` and `httpClient.defaults` are the Axios instance's own interceptor managers and defaults. Do not use the deprecated `interceptorsV1` and `defaultsV1` aliases in new code.

## Upload progress

`onUploadProgress` keeps working because Axios uses the XHR adapter in the browser. Axios 1.x passes an `AxiosProgressEvent` with `loaded`, `total`, `progress`, `rate` and `estimated`. Do not force the Axios fetch adapter through `httpClient.defaults.adapter`; fetch cannot report upload progress.

## TypeScript

Use Shopware's HTTP types instead of Axios types:

```typescript
import type { HttpClient, HttpError, HttpRequestConfig, HttpResponse } from 'src/core/factory/http-client.types';

class ExampleApiService {
    public constructor(private readonly httpClient: HttpClient) {}

    public getExample(config: HttpRequestConfig = {}): Promise<HttpResponse<ExampleData>> {
        return this.httpClient.get<ExampleData>('/api/example', config);
    }
}
```

The `axios-v1` module alias no longer exists. Code that imported types from `axios-v1` must import them from `axios`, or better, switch to the Shopware types above.

`HttpClient` remains structurally compatible with Axios' `AxiosInstance`, so existing code that passes the client to `axios-mock-adapter` keeps compiling. Do not depend on Axios implementation details beyond that: the concrete class of the client, version-specific properties, or internal interceptor handler arrays.

## Testing extensions

`axios-mock-adapter` continues to work with the facade:

```typescript
import MockAdapter from 'axios-mock-adapter';
import createHTTPClient from 'src/core/factory/http.factory';

const httpClient = createHTTPClient();
const mock = new MockAdapter(httpClient);

mock.onGet('/api/endpoint').reply(200, { data: 'test' });
```

Run the Administration tests with the major feature flags enabled to catch code that still depends on deprecated members:

```bash
FEATURE_ALL=major composer admin:unit
```

## Troubleshooting

### A deprecation warning mentions `useAxiosV1`

The option still works, but it will be removed. Check whether the request also works without it and remove it then. If it does not, keep `useAxiosV1: false` for now and report what breaks.

### A query string looks different than before

Axios 1.x percent-encodes `[`, `]`, `:`, `$` and `,`. Set `useAxiosV1: false` on the request to get the literal form back, or pass your own `paramsSerializer`.

### `response.headers` is no longer a plain object

Axios 1.x returns an `AxiosHeaders` instance. Lower-case property access keeps working; `Object.keys`, spreading and deep-equality assertions do not see the same object. Set `useAxiosV1: false` on the request to get a plain object back.

### A deprecation warning mentions `cancelToken`

Replace `CancelToken` with `AbortController` as shown above.

### Cancellation is not detected

Pass `controller.signal` as `signal` in the request config and check errors with `httpClient.isCancel(error)`.

### TypeScript expects an Axios-specific property

Use Shopware's `HttpClient`, `HttpRequestConfig`, `HttpResponse` and `HttpError` types. If the property is an Axios internal rather than part of the Shopware contract, remove that dependency.

## Additional resources

- [Original Shopware migration issue](https://github.com/shopware/shopware/issues/14041)
- [ADR: Keep Administration HTTP transports behind a compatibility facade](../../../../../../../adr/2026-07-23-administration-http-client-compatibility-facade.md)
- [ADR: Administration HTTP client runs on a single Axios 1.x transport](../../../../../../../adr/2026-09-11-administration-http-client-single-transport.md)
- [Axios v1 documentation](https://axios-http.com/docs/intro)
- [AbortController documentation](https://developer.mozilla.org/docs/Web/API/AbortController)
