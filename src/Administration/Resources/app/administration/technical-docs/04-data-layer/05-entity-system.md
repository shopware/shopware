# Entity System

The Entity System in Shopware 6 Administration provides reactive data objects with automatic change tracking, validation, and seamless integration with Vue.js. It forms the foundation for all data operations and state management within the administration interface.

## Entity Architecture

### Entity Class Structure

Entities in Shopware 6 are imported from the Meteor Admin SDK and enhanced with Vue.js reactivity:

```typescript
// Located in: core/data/entity.data.ts
import Entity, { assignSetterMethod } from '@shopware-ag/meteor-admin-sdk/es/_internals/data/Entity';

assignSetterMethod((draft, property, value) => {
    // Integration with Vue.js reactivity system
    Shopware.Application.view.setReactive(draft as Vue, property, value);
});
```

### Entity Creation

Entities are created through repositories and automatically configured with proper defaults:

```typescript
// Create new entity
const productRepository = this.repositoryFactory.create('product');
const newProduct = productRepository.create(context);

// Entity is automatically reactive and tracked
newProduct.name = 'New Product';
newProduct.active = true;
newProduct.stock = 100;
```

## Change Tracking System

### Draft/Origin Pattern

Every entity maintains two states for sophisticated change detection:

```typescript
const product = await productRepository.get(productId, context);

// Access original state (immutable)
const originalName = product.getOrigin().name;
const originalPrice = product.getOrigin().price;

// Access current draft state (mutable) - direct property access
const currentName = product.name;
const currentPrice = product.price;

// Make changes to draft
product.name = 'Updated Product Name';
product.price = 29.99;

// Check modification status
console.log(product.isNew());      // false (existing entity)
```

### Change Detection Methods

```typescript
// Check if entity is new (not yet persisted)
if (product.isNew()) {
    console.log('This is a new entity');
}

// Check if entity has changes using repository method
const productRepository = this.repositoryFactory.create('product');
if (productRepository.hasChanges(product)) {
    console.log('Entity has unsaved changes');
    await productRepository.save(product, context);
}

// The hasChanges method detects:
// - Field-level changes (comparing draft vs origin)
// - Association changes (added/removed/modified related entities)
// - Entities in the deletion queue

// Common usage pattern in components
export default {
    computed: {
        hasUnsavedChanges() {
            return this.product && this.productRepository.hasChanges(this.product);
        }
    },
    
    methods: {
        async onSave() {
            if (this.productRepository.hasChanges(this.product)) {
                await this.productRepository.save(this.product, this.context);
            }
        },
        
        canLeave() {
            // Used in route guards and navigation warnings
            return !this.productRepository.hasChanges(this.product);
        }
    }
};
```

### Field-Level Change Tracking

The changeset generator handles field-level change detection internally when saving entities:

```typescript
// The system automatically detects changes by comparing draft vs origin
// This happens during repository.save() operations

// You can manually check if values have changed
const hasNameChanged = product.name !== product.getOrigin().name;
const hasPriceChanged = product.price !== product.getOrigin().price;

// Get original value of a field
const originalValue = product.getOrigin().price;

// Get current value (direct property access)
const currentValue = product.price;
```

## Entity Properties and Metadata

### Basic Properties

```typescript
// Entity identification
console.log(product.id);                    // Entity ID
console.log(product.getEntityName());       // 'product'
console.log(product.getUniqueIdentifier()); // Composite primary key

// Timestamps (if available)
console.log(product.createdAt);
console.log(product.updatedAt);

// Entity state
console.log(product.isNew());
// Note: Use repository.hasChanges(entity) to check for modifications
// console.log(product.isDeleted()); // May not be available on all entity types
```

### Entity Extensions

Shopware 6 supports extending entities with custom fields:

```typescript
// Access custom fields through extensions
if (product.extensions && product.extensions.customFields) {
    console.log(product.extensions.customFields.myCustomField);
}

// Set custom field values
if (!product.extensions) {
    product.extensions = {};
}
if (!product.extensions.customFields) {
    product.extensions.customFields = {};
}
product.extensions.customFields.myCustomField = 'Custom Value';
```

## Entity Collections

### EntityCollection Structure

EntityCollection provides array-like functionality with additional entity-specific methods:

```typescript
// Located in: core/data/entity-collection.data.ts
import EntityCollection from '@shopware-ag/meteor-admin-sdk/es/_internals/data/EntityCollection';

const products = await productRepository.search(criteria, context);
console.log(products instanceof EntityCollection); // true
```

### Collection Methods

```typescript
// Basic collection operations
console.log(products.length);           // Number of entities
console.log(products.total);            // Total available (from search)
console.log(products.first());          // First entity or null
console.log(products.last());           // Last entity or null

// Get entity by ID
const product = products.get('product-id-123');

// Check if entity exists
if (products.has('product-id-123')) {
    console.log('Product exists in collection');
}

// Iterate over entities
products.forEach(product => {
    console.log(product.name);
});

// Filter collection
const activeProducts = products.filter(product => product.active);

// Map collection
const productNames = products.map(product => product.name);

// Find entity
const premiumProduct = products.find(product => 
    product.name.includes('Premium')
);
```

### Collection Manipulation

```typescript
// Add entity to collection
const newProduct = productRepository.create(context);
products.add(newProduct);

// Remove entity from collection
products.remove('product-id-to-remove');

// Clear collection
products.clear();

// Convert to plain array
const productArray = products.toArray();
```

## Entity Associations

### Loading Associations

Associations are loaded through criteria and accessed as properties:

```typescript
const criteria = new Criteria();
criteria.addAssociation('manufacturer');
criteria.addAssociation('categories');

const product = await productRepository.get(productId, context, criteria);

// Access single association (many-to-one)
console.log(product.manufacturer.name);

// Access collection association (one-to-many, many-to-many)
console.log(product.categories.length);
product.categories.forEach(category => {
    console.log(category.name);
});
```

### Association Types

```typescript
// Many-to-One (single entity)
const manufacturer = product.manufacturer;
if (manufacturer) {
    console.log('Manufacturer:', manufacturer.name);
}

// One-to-Many (collection)
const media = product.media;
media.forEach(mediaItem => {
    console.log('Media URL:', mediaItem.url);
});

// Many-to-Many (collection with join table)
const categories = product.categories;
categories.forEach(category => {
    console.log('Category:', category.name);
});

// One-to-One (single entity with foreign key)
const defaultPrice = product.price;
if (defaultPrice) {
    console.log('Price:', defaultPrice.gross);
}
```

### Modifying Associations

```typescript
// Set many-to-one association
product.manufacturerId = 'new-manufacturer-id';
// The manufacturer object will be updated after save/reload

// Add to many-to-many collection
const newCategory = categoryRepository.create(context);
newCategory.name = 'New Category';
product.categories.add(newCategory);

// Remove from collection
product.categories.remove('category-id-to-remove');

// Replace entire collection
const newCategories = new EntityCollection('/category', 'category', context);
newCategories.add(category1);
newCategories.add(category2);
product.categories = newCategories;
```

## Entity Validation

### Built-in Validation

Entities automatically validate based on entity definitions:

```typescript
// Required field validation
product.name = null; // Will trigger validation error on save

// Type validation (TypeScript compile-time)
product.active = 'invalid'; // TypeScript error
product.active = true;       // Correct

// Length validation (if defined in entity schema)
product.name = 'A'.repeat(300); // May trigger length validation
```

### Custom Validation

```typescript
// Add custom validation before save
function validateProduct(product) {
    const errors = [];
    
    if (!product.name || product.name.trim().length === 0) {
        errors.push('Product name is required');
    }
    
    if (product.price && product.price < 0) {
        errors.push('Price cannot be negative');
    }
    
    if (errors.length > 0) {
        throw new Error('Validation failed: ' + errors.join(', '));
    }
}

// Use before save
try {
    validateProduct(product);
    await productRepository.save(product, context);
} catch (error) {
    console.error('Validation error:', error.message);
}
```

## Entity Lifecycle Hooks

### Vue.js Integration

Entities integrate with Vue.js lifecycle and reactivity:

```typescript
export default {
    data() {
        return {
            product: null
        };
    },
    
    computed: {
        // Computed properties update when entity changes
        productDisplayName() {
            return this.product ? 
                `${this.product.name} (${this.product.productNumber})` : 
                '';
        },
        
        hasUnsavedChanges() {
            return this.product && this.productRepository.hasChanges(this.product);
        }
    },
    
    watch: {
        // Watch for entity changes
        'product.name': {
            handler(newName, oldName) {
                console.log('Product name changed:', oldName, '->', newName);
            }
        },
        
        hasUnsavedChanges: {
            handler(hasChanges) {
                if (hasChanges) {
                    this.showUnsavedChangesWarning = true;
                }
            }
        }
    }
};
```

### Entity Events

```typescript
// Listen for entity changes (custom implementation)
class EntityEventListener {
    constructor() {
        this.listeners = new Map();
    }
    
    on(entityName, event, callback) {
        const key = `${entityName}.${event}`;
        if (!this.listeners.has(key)) {
            this.listeners.set(key, []);
        }
        this.listeners.get(key).push(callback);
    }
    
    emit(entityName, event, entity, data) {
        const key = `${entityName}.${event}`;
        const callbacks = this.listeners.get(key) || [];
        callbacks.forEach(callback => callback(entity, data));
    }
}

// Usage
const eventListener = new EntityEventListener();

eventListener.on('product', 'save', (product, result) => {
    console.log('Product saved:', product.name);
});

eventListener.on('product', 'delete', (product) => {
    console.log('Product deleted:', product.name);
});
```

## Error Handling

### Entity-Level Errors

```typescript
try {
    await productRepository.save(product, context);
} catch (error) {
    if (error.response && error.response.data && error.response.data.errors) {
        const entityErrors = error.response.data.errors;
        
        entityErrors.forEach(err => {
            if (err.source && err.source.pointer) {
                const field = err.source.pointer.split('/').pop();
                console.log(`Error in field ${field}: ${err.detail}`);
            }
        });
    }
}
```

### Field-Specific Error Handling

```typescript
// Access field-specific errors from error store
const errorStore = Shopware.Store.get('error');
const fieldErrors = errorStore.getApiErrorsForEntity(product);

// Display errors in UI
Object.keys(fieldErrors).forEach(fieldName => {
    const errors = fieldErrors[fieldName];
    errors.forEach(error => {
        console.log(`${fieldName}: ${error.detail}`);
    });
});
```

## Performance Optimization

### Efficient Entity Usage

```typescript
// ✅ Good: Batch changes before save
product.name = 'New Name';
product.description = 'New Description';
product.active = true;
await productRepository.save(product, context); // Single API call

// ❌ Avoid: Multiple saves for related changes
product.name = 'New Name';
await productRepository.save(product, context);
product.description = 'New Description';
await productRepository.save(product, context); // Unnecessary additional call
```

### Memory Management

```typescript
// ✅ Good: Clear references when done
export default {
    beforeDestroy() {
        // Clear entity references to prevent memory leaks
        this.product = null;
        this.products = null;
    }
};

// ✅ Good: Use weak references for temporary entities
const weakEntityMap = new WeakMap();
weakEntityMap.set(component, entity); // Automatically cleaned up
```

### Association Loading Strategy

```typescript
// ✅ Good: Load associations in single request
const criteria = new Criteria();
criteria.addAssociation('manufacturer');
criteria.addAssociation('categories');
const product = await productRepository.get(id, context, criteria);

// ❌ Avoid: Loading associations separately
const product = await productRepository.get(id, context);
const manufacturer = await manufacturerRepository.get(product.manufacturerId, context);
const categories = await categoryRepository.search(categoryCriteria, context);
```

## Best Practices

### Entity State Management

1. **Always check entity state before operations**
2. **Use change tracking to optimize saves**
3. **Reset entities when discarding changes**
4. **Validate entities before persistence**

### Vue.js Integration

1. **Use computed properties for derived data**
2. **Watch entity changes for side effects**
3. **Clean up entity references in component lifecycle**
4. **Leverage Vue.js reactivity for UI updates**

### Error Handling

1. **Always handle save/load errors**
2. **Display field-specific validation errors**
3. **Provide user feedback for entity operations**
4. **Log errors for debugging purposes**

### Performance

1. **Batch entity operations when possible**
2. **Load associations efficiently**
3. **Use appropriate collection methods**
4. **Avoid unnecessary entity cloning**
