# API Services

API Services in Shopware 6 Administration handle specialized endpoints and operations that go beyond generic entity CRUD operations. They encapsulate complex business logic, bulk operations, and domain-specific functionality while maintaining consistent patterns for authentication, error handling, and HTTP communication.

## Service Architecture

### Base API Service

All API services extend the base `ApiService` class which provides:

```typescript
// Located in: core/service/api.service.ts
class ApiService {
    constructor(httpClient, loginService, apiEndpoint) {
        this.httpClient = httpClient;        // Axios instance
        this.loginService = loginService;    // Authentication service
        this.apiEndpoint = apiEndpoint;      // Base endpoint path
        this.name = 'apiService';            // Service identifier
    }
    
    getBasicHeaders(additionalHeaders = {}) {
        // Automatic authentication headers
        // Content-Type, Accept headers
        // CSRF protection
    }
}
```

### Service Registration

API services are registered through the service factory for dependency injection:

```typescript
// Service registration
Shopware.Service().register('serviceName', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    
    return new CustomApiService(
        initContainer.httpClient,
        container.loginService
    );
});

// Service usage
const customService = Shopware.Service('serviceName');
```

## Core API Services

### Sync API Service

Handles bulk operations and complex entity manipulations:

```typescript
// Located in: core/service/api/sync.api.service.js
class SyncApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'sync') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'syncService';
    }

    sync(payload, additionalParams = {}, additionalHeaders = {}) {
        const headers = this.getBasicHeaders(additionalHeaders);
        
        return this.httpClient.post(`/_action/${this.apiEndpoint}`, payload, {
            params: additionalParams,
            headers
        });
    }
}
```

**Usage Example:**
```typescript
const syncService = Shopware.Service('syncService');

await syncService.sync([
    {
        action: 'upsert',
        entity: 'product',
        payload: [
            { id: 'product-1', name: 'Updated Product 1' },
            { name: 'New Product 2', active: true }
        ]
    },
    {
        action: 'delete',
        entity: 'category',
        payload: [{ id: 'category-to-delete' }]
    }
]);
```

### System Config API Service

Manages system configuration and settings:

```typescript
// Located in: core/service/api/system-config.api.service.js
class SystemConfigApiService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, 'system-config');
        this.name = 'systemConfigApiService';
    }

    getValues(domain = null, salesChannelId = null) {
        const params = {};
        if (domain) params.domain = domain;
        if (salesChannelId) params.salesChannelId = salesChannelId;

        return this.httpClient.get(`/_action/${this.apiEndpoint}`, { params });
    }

    saveValues(values, salesChannelId = null) {
        const params = {};
        if (salesChannelId) params.salesChannelId = salesChannelId;

        return this.httpClient.post(`/_action/${this.apiEndpoint}`, values, { params });
    }
}
```

### Media API Service

Handles file uploads and media management:

```typescript
// Located in: core/service/api/media.api.service.js
class MediaApiService extends ApiService {
    uploadMediaById(mediaId, file, extension, fileName = file.name) {
        const url = `/_action/media/${mediaId}/upload`;
        const uploadParams = { extension, fileName };
        
        return this.httpClient.post(url, file, {
            params: uploadParams,
            headers: {
                'Content-Type': file.type,
                ...this.getBasicHeaders()
            }
        });
    }

    uploadMediaFromUrl(mediaId, url, extension, fileName) {
        const uploadUrl = `/_action/media/${mediaId}/upload`;
        
        return this.httpClient.post(uploadUrl, { url }, {
            params: { extension, fileName },
            headers: this.getBasicHeaders()
        });
    }
}
```

### Search API Service

Provides advanced search capabilities:

```typescript
// Located in: core/service/api/search.api.service.js
class SearchApiService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, 'search');
        this.name = 'searchService';
    }

    searchByEntity(entity, term, criteria = {}) {
        const searchUrl = `/_admin/${entity}/search`;
        
        return this.httpClient.post(searchUrl, {
            term,
            criteria
        });
    }

    searchGlobal(term, entities = []) {
        return this.httpClient.post('/_admin/search', {
            term,
            entities
        });
    }
}
```

## Common Service Patterns

### Authentication Integration

All API services automatically handle authentication:

```typescript
class CustomApiService extends ApiService {
    customMethod() {
        // Headers automatically include:
        // - Authorization: Bearer <token>
        // - sw-context-token: <context-token>
        // - Content-Type: application/json
        const headers = this.getBasicHeaders();
        
        return this.httpClient.get('/custom-endpoint', { headers });
    }
}
```

### Parameter Handling

Standard patterns for query parameters and headers:

```typescript
class CustomApiService extends ApiService {
    getData(filters = {}, additionalParams = {}, additionalHeaders = {}) {
        const params = {
            ...filters,
            ...additionalParams
        };
        
        const headers = this.getBasicHeaders(additionalHeaders);
        
        return this.httpClient.get('/data', { params, headers });
    }
}
```

## Specialized Services Examples

### Cache API Service

```typescript
class CacheApiService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, 'cache');
        this.name = 'cacheService';
    }

    clear() {
        return this.httpClient.delete(`/_action/${this.apiEndpoint}`);
    }

    clearByTag(tags) {
        return this.httpClient.post(`/_action/${this.apiEndpoint}/invalidate`, { tags });
    }

    warmup() {
        return this.httpClient.post(`/_action/${this.apiEndpoint}/warmup`);
    }
}
```

### Import/Export API Service

```typescript
class ImportExportApiService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, 'import-export');
        this.name = 'importExportService';
    }

    export(profileId, criteria = {}) {
        return this.httpClient.post(`/_action/${this.apiEndpoint}/export`, {
            profileId,
            criteria
        });
    }

    import(profileId, file) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('profileId', profileId);

        return this.httpClient.post(`/_action/${this.apiEndpoint}/import`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
                ...this.getBasicHeaders()
            }
        });
    }

    getProgress(logId) {
        return this.httpClient.get(`/_action/${this.apiEndpoint}/log/${logId}`);
    }
}
```

## Service Decoration and Extension

### Decorating Existing Services

Extend service functionality without modifying the original:

```typescript
Shopware.Service().registerDecorator('systemConfigApiService', (service) => {
    const originalSaveValues = service.saveValues;
    
    service.saveValues = async function(values, salesChannelId = null) {
        // Add logging
        console.log('Saving system config values:', values);
        
        // Add validation
        if (!this.validateValues(values)) {
            throw new Error('Invalid configuration values');
        }
        
        // Call original method
        const result = await originalSaveValues.call(this, values, salesChannelId);
        
        // Add post-processing
        this.notifyConfigChange(values);
        
        return result;
    };
    
    service.validateValues = function(values) {
        // Custom validation logic
        return true;
    };
    
    service.notifyConfigChange = function(values) {
        // Custom notification logic
    };
    
    return service;
});
```

### Creating Custom Services

```typescript
class CustomDomainService extends ApiService {
    constructor(httpClient, loginService) {
        super(httpClient, loginService, 'custom-domain');
        this.name = 'customDomainService';
    }

    getDomainData(domainId) {
        return this.httpClient.get(`/_action/${this.apiEndpoint}/${domainId}`);
    }

    processDomainLogic(data) {
        return this.httpClient.post(`/_action/${this.apiEndpoint}/process`, data);
    }

    // Domain-specific business logic
    async complexOperation(params) {
        const step1 = await this.getDomainData(params.id);
        const step2 = await this.processDomainLogic(step1.data);
        return step2;
    }
}

// Register the service
Shopware.Service().register('customDomainService', (container) => {
    const initContainer = Shopware.Application.getContainer('init');
    
    return new CustomDomainService(
        initContainer.httpClient,
        container.loginService
    );
});
```

## Service Middleware

Add cross-cutting concerns to all services:

```typescript
Shopware.Service().registerMiddleware('loggingMiddleware', (service, serviceName) => {
    // Wrap all service methods with logging
    Object.getOwnPropertyNames(Object.getPrototypeOf(service))
        .filter(method => typeof service[method] === 'function' && method !== 'constructor')
        .forEach(method => {
            const originalMethod = service[method];
            
            service[method] = function(...args) {
                console.log(`${serviceName}.${method} called with:`, args);
                const result = originalMethod.apply(this, args);
                
                if (result instanceof Promise) {
                    return result.then(response => {
                        console.log(`${serviceName}.${method} response:`, response);
                        return response;
                    });
                }
                
                return result;
            };
        });
    
    return service;
});
```

## Best Practices

### Service Organization
- One service per domain/entity type
- Keep services focused on API communication
- Move business logic to separate utility classes
- Use descriptive service names

### Error Handling
- Use try-catch blocks for async operations
- Provide meaningful error messages
- Log errors for debugging

### Performance
- Implement request caching where appropriate
- Use appropriate HTTP methods (GET, POST, PUT, DELETE)
- Batch operations when possible
- Avoid unnecessary API calls

### Testing
- Mock HTTP client for unit tests
- Test error scenarios
- Verify authentication headers
- Test parameter handling

```typescript
// Example service test
describe('CustomApiService', () => {
    let service;
    let httpClient;
    
    beforeEach(() => {
        httpClient = {
            get: jest.fn(),
            post: jest.fn()
        };
        
        service = new CustomApiService(httpClient, {});
    });
    
    it('should call correct endpoint with proper headers', async () => {
        httpClient.get.mockResolvedValue({ data: 'test' });
        
        await service.customMethod();
        
        expect(httpClient.get).toHaveBeenCalledWith(
            '/custom-endpoint',
            expect.objectContaining({
                headers: expect.objectContaining({
                    'Content-Type': 'application/json'
                })
            })
        );
    });
});
```
