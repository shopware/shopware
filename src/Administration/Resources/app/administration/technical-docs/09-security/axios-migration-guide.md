# Axios v0 to v1 Migration Guide

## Overview

The Shopware Administration has upgraded from axios 0.30.2 to axios 1.x to address a critical security vulnerability (CVE-2023-45857). The upgrade is implemented using a dual-client dispatcher pattern that allows both versions to coexist, enabling a gradual migration path for plugins and custom code.

## Security Vulnerability

Axios 0.30.2 contains a security vulnerability (CVE-2023-45857) where the library follows HTTP redirects to potentially insecure protocols. This vulnerability can be exploited in certain attack scenarios. Upgrading to axios 1.x addresses this vulnerability.

## Architecture

The Administration now runs two axios versions simultaneously:
- **axios 0.30.2** - Default for backward compatibility
- **axios 1.x** - Opt-in via `useAxiosV1` flag

A dispatcher function in the HTTP factory routes requests to the appropriate axios version based on the `useAxiosV1` flag in the request configuration.

## How to Opt-In to Axios v1

### Direct HTTP Client Usage

When using the HTTP client directly, add `useAxiosV1: true` to your request configuration:

```javascript
// Inject the httpClient
inject: ['httpClient']

// Use axios v1 for this request
this.httpClient.request({
    method: 'get',
    url: '/api/endpoint',
    useAxiosV1: true,
});

// Or with convenience methods
this.httpClient.post('/api/endpoint', data, {
    useAxiosV1: true,
});

this.httpClient.get('/api/endpoint', {
    useAxiosV1: true,
});
```

### Repository Usage

When using repositories, the HTTP client is used internally. While repositories don't expose the `useAxiosV1` flag directly in their methods, the underlying HTTP client respects the flag when making requests.

```javascript
// Repository calls use the default axios v0
const product = await this.productRepository.get(productId, context);

// To use axios v1, you would need to use the HTTP client directly
```

### API Services

API services that extend `ApiService` use `this.httpClient` internally. The services will automatically use the dispatcher:

```javascript
// In your API service
this.httpClient.get(url, {
    headers: this.getBasicHeaders(),
    useAxiosV1: true, // Opt-in to axios v1
});
```

## Key Differences Between Axios v0 and v1

### Request Cancellation

**Axios v0** uses `CancelToken`:
```javascript
const { CancelToken } = Axios;
const source = CancelToken.source();

httpClient.get('/api/endpoint', {
    cancelToken: source.token,
});

// Cancel the request
source.cancel('Operation cancelled');

// Check if error is a cancellation
if (Axios.isCancel(error)) {
    // Handle cancellation
}
```

**Axios v1** uses `AbortController` (modern standard):
```javascript
const controller = new AbortController();

httpClient.get('/api/endpoint', {
    signal: controller.signal,
    useAxiosV1: true,
});

// Cancel the request
controller.abort();

// Check if error is a cancellation
if (error.name === 'CanceledError' || error.code === 'ERR_CANCELED') {
    // Handle cancellation
}
```

**Note:** The dispatcher's `isCancel()` method checks for both cancellation types, so you can use it for both versions:
```javascript
if (httpClient.isCancel(error)) {
    // Works for both v0 and v1 cancellations
}
```

### Error Response Structure

Error responses are similar between versions, but there are subtle differences:

**Axios v0:**
```javascript
error.response.status
error.response.data
error.response.headers
```

**Axios v1:**
```javascript
error.response.status  // Same
error.response.data    // Same
error.response.headers // Same
error.code             // More standardized codes like 'ERR_BAD_REQUEST'
```

### Interceptors

Both versions support interceptors, and the HTTP factory applies the same interceptors to both axios instances. There are no changes needed for custom interceptors.

## Feature Flag: V6_8_0_0

Starting with Shopware 6.8, the default axios version will be switched to v1 via the `V6_8_0_0` feature flag.

### Current Behavior (6.7.x)
- Default: axios v0
- Opt-in: Add `useAxiosV1: true`

### Future Behavior (6.8.0+)
- Default: axios v1 (when `V6_8_0_0` flag is active)
- Opt-out: Add `useAxiosV1: false` (if you still need v0)

The dispatcher logic:
```javascript
const shouldUseV1 = config.useAxiosV1 ?? Shopware.Feature.isActive('V6_8_0_0');
```

## Migration Timeline

1. **Shopware 6.7.0** - Dual-client dispatcher introduced, default is axios v0
2. **Shopware 6.7.x** - Plugin developers can opt-in to axios v1 for testing
3. **Shopware 6.8.0** - `V6_8_0_0` flag enables axios v1 as default
4. **Future Release** - Axios v0 will be removed entirely, `useAxiosV1` flag deprecated

## Testing Your Code

### Unit Tests

When writing unit tests with `axios-mock-adapter`, the library now supports both axios versions (v2.1.0+):

```javascript
import MockAdapter from 'axios-mock-adapter';
import createHTTPClient from 'src/core/factory/http.factory';

const httpClient = createHTTPClient();
const mock = new MockAdapter(httpClient);

// Mock still works as before
mock.onGet('/api/endpoint').reply(200, { data: 'test' });
```

### Integration Testing

Test your plugin with both axios versions:

1. Test with default (v0): Normal operation
2. Test with `useAxiosV1: true`: Explicitly opt-in
3. Test with `V6_8_0_0` flag active: Simulates 6.8 behavior

## Recommendations

1. **Start Testing Early**: Add `useAxiosV1: true` to your API calls in development and test thoroughly
2. **Focus on Critical Paths**: Prioritize testing critical API operations first
3. **Watch for Cancellations**: If you use request cancellation, update to AbortController for axios v1
4. **Monitor Errors**: Check error handling code for version-specific assumptions
5. **Prepare for 6.8**: Ensure your code works with axios v1 before the 6.8 release

## Common Issues

### Issue: Request cancellation not working with v1
**Solution**: Use `AbortController` instead of `CancelToken` when `useAxiosV1: true`

### Issue: Error checking fails
**Solution**: Use the dispatcher's `isCancel()` method which works for both versions

### Issue: Custom interceptors not working
**Solution**: The HTTP factory applies interceptors to both clients automatically. If you're adding interceptors after client creation, ensure they're added to the correct instance.

## Need Help?

If you encounter issues or have questions about the axios migration:
1. Check this guide for common patterns
2. Review the implementation in `src/core/factory/http.factory.js`
3. Look at the adapter code in `src/core/factory/http-client-adapter.ts`
4. Consult the test files for examples: `http.factory.spec.js` and `http-client-adapter.spec.ts`

## Additional Resources

- [Axios v1 Migration Guide (Official)](https://github.com/axios/axios/blob/v1.x/MIGRATION_GUIDE.md)
- [CVE-2023-45857 Details](https://nvd.nist.gov/vuln/detail/CVE-2023-45857)
- [AbortController MDN Documentation](https://developer.mozilla.org/en-US/docs/Web/API/AbortController)

