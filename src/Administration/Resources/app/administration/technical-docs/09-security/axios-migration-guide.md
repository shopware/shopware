# Administration HTTP client: Axios 1.x

## Overview

The Shopware Administration HTTP client (`httpClient`) is a single Axios 1.x instance behind a Shopware-owned facade. The legacy Axios 0.x transport, the per-request transport switch, and the dual-transport compatibility machinery have been removed.

Extension code continues to use the injected `httpClient`. Axios stays an implementation detail: the facade, not Axios, is the extension boundary, and Shopware's HTTP types describe the supported contract.

## What changed

| Before | Now |
| --- | --- |
| Two Axios copies (`axios` 0.x and `axios-v1`) | One `axios` 1.x dependency |
| `useAxiosV1` selected the transport per request | `useAxiosV1` has no effect and is deprecated |
| `CancelToken` cancelled Axios 0.x requests | `AbortController` cancels requests; `CancelToken` is deprecated |
| `axiosV0`, `interceptorsV0`, `defaultsV0` escape hatches | Removed |
| `axiosV1`, `interceptorsV1`, `defaultsV1` escape hatches | Deprecated aliases of `httpClient`, `httpClient.interceptors`, `httpClient.defaults` |

All deprecated members stay functional until the next major and log a deprecation warning in development mode the first time they are used.

## Migrating direct HTTP requests

Remove the `useAxiosV1` option. Everything else stays the same:

```javascript
// Before
this.httpClient.get('/api/endpoint', { useAxiosV1: true });

// Now
this.httpClient.get('/api/endpoint');
```

API services extending `ApiService` need no further change.

## Repository requests

No change is required:

```javascript
const product = await this.productRepository.get(productId, context);
```

Repositories never exposed the transport as part of their contract.

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

Remove the option from the request configuration. It has no effect anymore.

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
