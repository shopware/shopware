# Legacy Axios to Axios v1 Migration Guide

## Overview

The Shopware Administration is moving from its legacy Axios 0.x client to Axios 1.x. Axios 1.x is the maintained release line and provides current security fixes, bug fixes, and ecosystem compatibility.

To keep existing extensions working during the migration, the Administration temporarily contains both Axios versions behind a Shopware-owned HTTP client facade. Extension code continues to use the injected `httpClient`; it must not select or access an underlying Axios instance.

The exact installed patch versions are implementation details and may change while the migration is in progress. This guide therefore refers to the clients as legacy Axios 0.x and Axios v1.

## Version selection

The HTTP client selects the transport in this order:

1. An explicit `useAxiosV1` value in the request configuration
2. The `V6_8_0_0` feature flag
3. The compatibility default for the installed Shopware version

The resulting behavior is:

| Context | Default transport | Temporary override |
| --- | --- | --- |
| Direct HTTP requests on Shopware 6.7 | Legacy Axios | `useAxiosV1: true` |
| Direct HTTP requests with `V6_8_0_0` active | Axios v1 | `useAxiosV1: false` |
| Repository requests during the transition | Axios v1 | None; the transport is internal |

Repository requests use Axios v1 before the global switch because repositories are the standard Administration data-access path. Axios is not part of the repository contract, so extensions do not select its transport or need to change repository calls.

## Migrating direct HTTP requests

Opt in while running Shopware 6.7 by setting `useAxiosV1: true`:

```javascript
// Injected by the Administration
inject: ['httpClient']

this.httpClient.get('/api/endpoint', {
    useAxiosV1: true,
});

this.httpClient.post('/api/endpoint', data, {
    useAxiosV1: true,
});
```

API services extending `ApiService` use the same facade:

```javascript
this.httpClient.get(url, {
    headers: this.getBasicHeaders(),
    useAxiosV1: true,
});
```

Keep the explicit opt-in while validating Axios v1 on Shopware 6.7. Remove it after upgrading to a version where `V6_8_0_0` is active, because Axios v1 is then the default.

## Repository requests

No change is normally required:

```javascript
const product = await this.productRepository.get(productId, context);
```

If a repository operation behaves differently with Axios v1, treat it as a Shopware compatibility issue. Repository consumers should not work around it by selecting an HTTP transport.

## TypeScript

New Administration code should use Shopware's HTTP types instead of importing `AxiosInstance`:

```typescript
import type { HttpClient, HttpRequestConfig, HttpResponse } from 'src/core/factory/http-client.types';

class ExampleApiService {
    public constructor(private readonly httpClient: HttpClient) {}

    public getExample(config: HttpRequestConfig = {}): Promise<HttpResponse<ExampleData>> {
        return this.httpClient.get<ExampleData>('/api/example', config);
    }
}
```

During the transition, the facade remains structurally compatible with the previously exposed `AxiosInstance` type. Existing code using `AxiosRequestConfig.useAxiosV1` or passing the client to `axios-mock-adapter` therefore does not require a compatibility cast.

This structural compatibility is transitional. Do not depend on Axios implementation details such as:

- The identity or concrete class of the underlying client
- Version-specific client properties
- Internal interceptor handler arrays
- Axios-specific defaults not declared by Shopware's `HttpClient`

## Request cancellation

Legacy Axios uses `CancelToken`:

```javascript
const source = httpClient.CancelToken.source();

httpClient.get('/api/endpoint', {
    cancelToken: source.token,
    useAxiosV1: false,
});

source.cancel('Operation cancelled');
```

Axios v1 uses `AbortController`:

```javascript
const controller = new AbortController();

httpClient.get('/api/endpoint', {
    signal: controller.signal,
    useAxiosV1: true,
});

controller.abort();
```

Use the facade to detect cancellation errors from either transport:

```javascript
if (httpClient.isCancel(error)) {
    // Works for both cancellation mechanisms
}
```

## Errors

The common response properties remain available:

```javascript
error.response.status
error.response.data
error.response.headers
```

Do not depend on the exact error class or message. Axios v1 provides standardized error codes such as `ERR_CANCELED`; use `httpClient.isCancel(error)` when handling cancellation across both transports.

## Interceptors and defaults

Register customizations through the existing facade:

```javascript
const interceptorId = httpClient.interceptors.request.use(myRequestHandler);
httpClient.defaults.headers.common['my-header'] = 'value';

httpClient.interceptors.request.eject(interceptorId);
```

The facade registers the interceptor and applies default mutations to both internal clients. A request is still handled by only one transport, so a mirrored interceptor runs once per request. The returned interceptor ID belongs to the facade and must be passed back to the same facade for ejection.

Do not register separate version-specific interceptors or defaults.

## Testing extensions

Test all transport-sensitive paths before relying on the 6.8 default:

1. Test direct requests with `useAxiosV1: false`
2. Test direct requests with `useAxiosV1: true`
3. Test normal repository calls, which already use Axios v1
4. Test request cancellation and error handling
5. Run the Administration tests with all major feature flags enabled

For the Shopware platform test suite:

```bash
FEATURE_ALL=major composer admin:unit
```

`axios-mock-adapter` continues to work with the facade:

```typescript
import MockAdapter from 'axios-mock-adapter';
import createHTTPClient from 'src/core/factory/http.factory';

const httpClient = createHTTPClient();
const mock = new MockAdapter(httpClient);

mock.onGet('/api/endpoint').reply(200, { data: 'test' });
```

## Temporary fallback

When `V6_8_0_0` is active, use `useAxiosV1: false` only when an incompatibility prevents an immediate migration:

```javascript
httpClient.request({
    method: 'get',
    url: '/api/endpoint',
    useAxiosV1: false,
});
```

Avoid spreading this override across an extension. A widespread opt-out hides migration problems and makes later removal harder.

Axios 0.x, the `useAxiosV1` switch, and structural `AxiosInstance` compatibility are transitional. Their removal will be announced through the release information and the applicable major upgrade guide. No specific removal release is promised by this guide.

The architectural rationale for keeping both transports behind a Shopware-owned boundary is documented in [Keep Administration HTTP transports behind a compatibility facade](../../../../../../../adr/2026-07-23-administration-http-client-compatibility-facade.md).

## Troubleshooting

### Cancellation is not detected

Use `AbortController` for Axios v1 requests and check errors with `httpClient.isCancel(error)`.

### A custom interceptor or default affects only some requests

Register it through `httpClient.interceptors` or `httpClient.defaults`. Do not retain or configure an underlying Axios instance.

### A repository request behaves differently

Reproduce the request with Axios v1 and check cancellation and error assumptions. The repository transport cannot be changed by consumers; report the compatibility issue so it can be fixed centrally.

### TypeScript expects an Axios-specific property

Use Shopware's `HttpClient`, `HttpRequestConfig`, and `HttpResponse` types. If the property is an Axios internal rather than part of the Shopware contract, remove that dependency.

## Additional resources

- [Original Shopware migration issue](https://github.com/shopware/shopware/issues/14041)
- [Axios v1 documentation](https://axios-http.com/docs/intro)
- [AbortController documentation](https://developer.mozilla.org/docs/Web/API/AbortController)
