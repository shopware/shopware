# The Administration HTTP client

## Overview

The Administration performs HTTP requests through a Shopware-owned HTTP client facade backed by Axios 1.x. Extension code uses the injected `httpClient` and does not access an underlying Axios instance.

Until Shopware 6.8 the Administration shipped a second, legacy Axios 0.x transport and a per-request `useAxiosV1` switch. Both were removed with 6.8. If you are migrating an extension from 6.7, follow the "Migrating from Axios 0.x" section at the end of this guide.

## Making requests

The HTTP client is injected as `httpClient` and applies the API base URL, authentication, token refresh and error handling:

```javascript
inject: ['httpClient']

this.httpClient.get('/api/endpoint');
this.httpClient.post('/api/endpoint', data);
```

API services extending `ApiService` use the same client:

```javascript
this.httpClient.get(url, {
    headers: this.getBasicHeaders(),
});
```

Repository requests go through the same client. Axios is not part of the repository contract:

```javascript
const product = await this.productRepository.get(productId, context);
```

## TypeScript

Use Shopware's HTTP types instead of importing `AxiosInstance`:

```typescript
import type { HttpClient, HttpRequestConfig, HttpResponse } from 'src/core/factory/http-client.types';

class ExampleApiService {
    public constructor(private readonly httpClient: HttpClient) {}

    public getExample(config: HttpRequestConfig = {}): Promise<HttpResponse<ExampleData>> {
        return this.httpClient.get<ExampleData>('/api/example', config);
    }
}
```

Do not depend on Axios implementation details such as:

- The identity or concrete class of the underlying client
- Internal interceptor handler arrays
- Axios-specific defaults not declared by Shopware's `HttpClient`

## Request cancellation

Use `AbortController`:

```javascript
const controller = new AbortController();

httpClient.get('/api/endpoint', {
    signal: controller.signal,
});

controller.abort();
```

Detect a cancelled request with the client:

```javascript
if (httpClient.isCancel(error)) {
    // Request was cancelled
}
```

`httpClient.CancelToken` and the `cancelToken` request option still exist because Axios 1.x still supports them. Axios deprecates both; use `AbortController` in new code.

## Errors

The common response properties are available on the error:

```javascript
error.response.status
error.response.data
error.response.headers
```

Do not depend on the exact error class or message. Axios reports standardized codes such as `ERR_CANCELED`.

## Interceptors and defaults

Register customizations on the client:

```javascript
const interceptorId = httpClient.interceptors.request.use(myRequestHandler);
httpClient.defaults.headers.common['my-header'] = 'value';

httpClient.interceptors.request.eject(interceptorId);
```

## Testing extensions

`axios-mock-adapter` works with the client:

```typescript
import MockAdapter from 'axios-mock-adapter';
import createHTTPClient from 'src/core/factory/http.factory';

const httpClient = createHTTPClient();
const mock = new MockAdapter(httpClient);

mock.onGet('/api/endpoint').reply(200, { data: 'test' });
```

## Migrating from Axios 0.x

Shopware 6.8 removed the legacy transport and its compatibility surface:

| Removed | Replacement |
| --- | --- |
| The `useAxiosV1` request flag | Nothing. Delete it. |
| `httpClient.axiosV0`, `httpClient.axiosV1` | `httpClient` |
| `httpClient.interceptorsV0`, `httpClient.interceptorsV1` | `httpClient.interceptors` |
| `httpClient.defaultsV0`, `httpClient.defaultsV1` | `httpClient.defaults` |
| The `axios-v1` package alias | `axios`, which resolves to 1.x |
| `src/core/factory/http-client-adapter` | Nothing. Call the HTTP client directly. |

Steps:

1. Remove every `useAxiosV1`, in both directions.
2. Replace `CancelToken` based cancellation with `AbortController`.
3. Replace `axios-v1` imports with `axios`, or better, with Shopware's HTTP types.
4. Replace access to `axiosV0`, `axiosV1`, `interceptorsV*` and `defaults*V*` with `httpClient.interceptors` and `httpClient.defaults`.

The removal is documented in `UPGRADE-6.8.md`, section "Axios 1.x is the only HTTP client of the Administration".

## Troubleshooting

### Cancellation is not detected

Use `AbortController` and check errors with `httpClient.isCancel(error)`.

### A custom interceptor or default affects only some requests

Register it through `httpClient.interceptors` or `httpClient.defaults`. Do not retain or configure a separate Axios instance.

### TypeScript expects an Axios-specific property

Use Shopware's `HttpClient`, `HttpRequestConfig` and `HttpResponse` types. If the property is an Axios internal rather than part of the Shopware contract, remove that dependency.

## Additional resources

- [Original Shopware migration issue](https://github.com/shopware/shopware/issues/14041)
- [Axios documentation](https://axios-http.com/docs/intro)
- [AbortController documentation](https://developer.mozilla.org/docs/Web/API/AbortController)
