# Repository Pattern

The Repository Pattern in Shopware 6 Administration provides a consistent, type-safe interface for entity CRUD operations and complex queries. It abstracts the underlying HTTP API calls and provides automatic data hydration, change tracking, and error handling.

## Repository Factory

The `RepositoryFactory` is the central entry point for creating repository instances:

```typescript
// Located in: core/data/repository-factory.data.ts
const repositoryFactory = Shopware.Service('repositoryFactory');
const productRepository = repositoryFactory.create('product');
```

### Factory Dependencies

The repository factory injects several key services into each repository:

- **EntityHydrator**: Converts raw API responses to reactive entity objects
- **ChangesetGenerator**: Computes minimal change sets for save operations
- **EntityFactory**: Creates new entity instances with proper defaults
- **HttpClient**: Axios instance for API communication
- **ErrorResolver**: Maps API errors to field-specific validation messages

### Automatic Route Resolution

Entity names are automatically converted to API routes:
- `product` → `/product`
- `product_category` → `/product-category`

## Repository Interface

Each repository provides a consistent set of methods for data operations:

### Search Operations

```typescript
// Basic search with criteria
const criteria = new Criteria(1, 25);
criteria.addFilter(Criteria.equals('active', true));
criteria.addSorting(Criteria.sort('name', 'ASC'));

const searchResult = await productRepository.search(criteria, context);
```

**Key Methods:**
- `search(criteria, context)`: Returns EntityCollection with entities and metadata
- `searchIds(criteria, context)`: Returns only entity IDs for efficient lookups
- `get(id, context, criteria)`: Fetch single entity by ID

### CRUD Operations

```typescript
// Create new entity
const newProduct = productRepository.create(context);
newProduct.name = 'New Product';
newProduct.active = true;

// Save entity (create or update)
await productRepository.save(newProduct, context);

// Delete entity
await productRepository.delete(productId, context);

// Clone entity
const clonedProduct = await productRepository.clone(productId, context, criteria);
```

### Bulk Operations

```typescript
// Save multiple entities in a single request
await productRepository.saveAll([product1, product2, product3], context);

// Delete multiple entities
await productRepository.deleteAll([id1, id2, id3], context);

// Sync operation for complex bulk updates
await productRepository.sync([
    { action: 'upsert', entity: 'product', payload: [product1, product2] },
    { action: 'delete', entity: 'product', payload: [{ id: 'id-to-delete' }] }
], context);
```

## Entity Lifecycle Management

### Change Tracking

Repositories automatically track entity changes through the draft/origin pattern:

```typescript
const product = await productRepository.get(productId, context);

// Original state preserved in entity.getOrigin()
console.log(product.getOrigin().name); // "Original Name"

// Modifications tracked in entity.getDraft()
product.name = "Modified Name";
console.log(product.getDraft().name); // "Modified Name"

// Check if entity has been modified using repository
console.log(productRepository.hasChanges(product)); // true
```

### Changeset Generation

The `ChangesetGenerator` computes minimal payloads for API requests:

```typescript
// Only sends modified fields to the API
product.name = "New Name";          // Will be included
product.description = "New Desc";   // Will be included
// product.price unchanged          // Will NOT be included

await productRepository.save(product, context);
```

### Association Handling

The repository pattern handles complex entity relationships:

```typescript
const criteria = new Criteria();
criteria.addAssociation('categories');
criteria.addAssociation('manufacturer');

const product = await productRepository.get(productId, context, criteria);

// Access loaded associations
console.log(product.categories);    // EntityCollection<Category>
console.log(product.manufacturer);  // Entity<Manufacturer>
```

**Association Types Supported:**
- **OneToMany**: Collections of related entities (product.media)
- **ManyToOne**: Single related entity (product.manufacturer)
- **ManyToMany**: Collections with join table (product.categories)
- **OneToOne**: Single related entity with foreign key

## TypeScript Integration

Repositories provide full TypeScript support through generic typing. In most cases, the types are inferred automatically:

```typescript
// Automatic type inference
const repositoryFactory = Shopware.Service('repositoryFactory');
const productRepository = repositoryFactory.create('product');

// Explicit typing (optional)
const productRepository: Repository<'product'> = repositoryFactory.create('product');

// Type-safe entity access
const product: Entity<'product'> = await productRepository.get(id, context);

// TypeScript will enforce entity schema
product.name = "string";     // ✅ Valid
product.name = 123;          // ❌ Type error
product.nonExistentField;    // ❌ Type error
```

## Context and Authentication

All repository operations require a context object that provides:

```typescript
const context = Shopware.Context.api;  // Default API context

// Context includes:
// - Authentication headers
// - Language/locale settings
// - API version information
// - Inheritance configuration
```

But you don't need to provide it manually in most cases, as the default context is sufficient for standard operations and is automatically used by the repositories.

## Error Handling

Repositories integrate with the error handling system:

```typescript
try {
    await productRepository.save(product, context);
} catch (error) {
    // Errors automatically mapped to entity fields
    if (error.response?.data?.errors) {
        // Field-specific errors available in error store
        const fieldErrors = Shopware.Store.get('error').getApiErrorsForEntity(product);
    }
}
```

### Component Error Mapping

Shopware provides a convenient helper for mapping entity errors directly to component computed properties:

```javascript
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

export default {
    // ...existing code...
    
    computed: {
        // ...existing code...
        
        // Maps errors for the 'manufacturer' entity and 'name' field
        // Creates a computed property 'manufacturerNameError'
        ...mapPropertyErrors('manufacturer', ['name']),
        
        // For multiple fields:
        ...mapPropertyErrors('product', ['name', 'price', 'stock']),
        // Creates: productNameError, productPriceError, productStockError
    }
};
```

**Template Usage:**
```html
<sw-text-field
    v-model="manufacturer.name"
    :label="$tc('sw-manufacturer.detail.labelName')"
    name="name"
    validation="required"
    required
    :error="manufacturerNameError"
    :disabled="!acl.can('product_manufacturer.editor')"
/>
```

The `mapPropertyErrors` helper automatically:
- Listens to the error store for the specified entity and fields
- Returns the first error message for each field
- Updates reactively when errors change or are cleared
- Provides null when no errors exist for the field

## Performance Best Practices

### Repository Instance Reuse
```typescript
// ✅ Good: Reuse repository within component scope
export default {
    inject: ['repositoryFactory'],
    
    created() {
        this.productRepository = this.repositoryFactory.create('product');
    },
    
    methods: {
        async loadProducts() {
            return this.productRepository.search(criteria, context);
        }
    }
};
```

### Pagination Strategy
```typescript
// ✅ Good: Use appropriate page sizes
const criteria = new Criteria(1, 25);  // Reasonable page size

// ❌ Avoid: Large page sizes
const criteria = new Criteria(1, 1000);  // May impact performance

// ✅ Good: Disable total count when not needed
criteria.setTotalCountMode(0);  // Improves performance
```

### Change Detection Optimization
```typescript
// ✅ Good: Batch multiple changes before save
product.name = "New Name";
product.description = "New Description";
product.active = false;
await productRepository.save(product, context);  // Single API call

// ❌ Avoid: Multiple individual saves
await productRepository.save(product, context);  // Save after each change
product.description = "New Description";
await productRepository.save(product, context);  // Unnecessary API calls
```

### Post-Save Data Refresh
```typescript
// ✅ Good: Reload entity after save to sync origin state
async saveProduct(product) {
    try {
        await this.productRepository.save(product, this.context);
        
        // Reload to ensure proper change tracking
        this.product = await this.productRepository.get(product.id, this.context, this.criteria);
        
        // Now change detection works correctly
        // If user changes a value back to what it was before the save,
        // the system will correctly detect it as "no change"
        
    } catch (error) {
        // Handle errors
    }
}

// ❌ Avoid: Not reloading after save
async saveProductBad(product) {
    await this.productRepository.save(product, this.context);
    
    // Problem: draft state is still the modified version
    // origin state is outdated (pre-save state)
    // Changing values back to pre-save state won't be detected as "no change"
    
    console.log(this.productRepository.hasChanges(product)); // May incorrectly return true
}
```

**Why reload after save?**
- **Origin State Update**: The entity's origin needs to reflect the current server state
- **Change Tracking Accuracy**: Ensures future modifications are correctly detected
- **Server-Side Changes**: Captures any server-side modifications (timestamps, calculated fields, etc.)
- **Consistent State**: Prevents drift between client and server state

## Extension and Customization

### Repository Decoration
```typescript
// Extend repository behavior through service decorators
Shopware.Service().registerDecorator('repositoryFactory', (service) => {
    const originalCreate = service.create;
    
    service.create = function(entityName, route, options) {
        const repository = originalCreate.call(this, entityName, route, options);
        
        // Add custom methods or override behavior
        repository.customMethod = function() {
            // Custom logic here
        };
        
        return repository;
    };
    
    return service;
});
```

### Custom Repository Methods
```typescript
// Add domain-specific methods to repositories
class ProductRepository extends Repository {
    async findActiveProducts(criteria, context) {
        criteria.addFilter(Criteria.equals('active', true));
        return this.search(criteria, context);
    }
    
    async bulkUpdatePrices(priceUpdates, context) {
        const operations = priceUpdates.map(update => ({
            action: 'upsert',
            entity: 'product',
            payload: [{ id: update.id, price: update.price }]
        }));
        
        return this.sync(operations, context);
    }
}
```
