# Change Tracking and Error Handling

Shopware 6 Administration provides sophisticated change tracking through the Changeset Generator and comprehensive error handling through the Error Resolver. These systems ensure data integrity, optimize API performance, and provide meaningful feedback to users.

## Changeset Generator

The Changeset Generator computes minimal change sets by comparing entity origin and draft states, significantly reducing API payload sizes and improving performance.

### Core Functionality

```typescript
// Located in: core/data/changeset-generator.data.js
class ChangesetGenerator {
    /**
     * Creates the change set for the provided entity.
     * Returns object with changes and deletionQueue
     */
    generate(entity) {
        const deletionQueue = [];
        const changes = this.recursion(entity, deletionQueue);
        
        return { changes, deletionQueue };
    }
}
```

### Change Detection Process

The changeset generator follows a sophisticated algorithm to detect changes:

```typescript
// Example of change detection logic
const product = await productRepository.get(productId, context);

// Original state (immutable)
const origin = product.getOrigin();
console.log('Original name:', origin.name);
console.log('Original price:', origin.price);

// Draft state (mutable)
const draft = product.getDraft();
product.name = 'Updated Product Name';
product.price = 29.99;

console.log('Draft name:', draft.name);
console.log('Draft price:', draft.price);

// Generate changeset
const changesetGenerator = new ChangesetGenerator();
const { changes, deletionQueue } = changesetGenerator.generate(product);

console.log('Changes to send to API:', changes);
// Output: { name: 'Updated Product Name', price: 29.99 }
// Note: Only modified fields are included
```

### Field Type Handling

The changeset generator handles different field types appropriately:

#### Scalar Fields
```typescript
// Simple value comparison for scalar types
if (draftValue !== originValue) {
    changes[fieldName] = draftValue;
}

// Supported scalar types: uuid, int, text, password, float, string, blob, boolean, date
```

#### JSON Fields
```typescript
// Deep comparison for JSON objects and arrays
if (!types.isEqual(originValue, draftValue)) {
    if (Array.isArray(draftValue) && draftValue.length <= 0) {
        changes[fieldName] = [];
    } else {
        changes[fieldName] = draftValue;
    }
}

// Handles: json_list, json_object types
```

#### Association Fields
```typescript
// Different handling based on association type
switch (field.relation) {
    case 'one_to_many':
        const associationChanges = this.handleOneToMany(field, draftValue, originValue, deletionQueue);
        if (associationChanges.length > 0) {
            changes[fieldName] = associationChanges;
        }
        break;
        
    case 'many_to_many':
        const manyToManyChanges = this.handleManyToMany(draftValue, originValue, deletionQueue, field, entity);
        if (manyToManyChanges.length > 0) {
            changes[fieldName] = manyToManyChanges;
        }
        break;
        
    case 'one_to_one':
        if (draftValue && this.changesetGenerator.hasChanges(draftValue)) {
            changes[fieldName] = this.recursion(draftValue, deletionQueue);
        }
        break;
}
```

### Association Change Handling

#### One-to-Many Associations
```typescript
handleOneToMany(field, draftCollection, originCollection, deletionQueue) {
    const changes = [];
    
    // Handle new entities
    draftCollection.forEach(entity => {
        if (entity.isNew()) {
            changes.push(this.recursion(entity, deletionQueue));
        } else if (entity.isModified()) {
            changes.push(this.recursion(entity, deletionQueue));
        }
    });
    
    // Handle deleted entities
    originCollection.forEach(entity => {
        if (!draftCollection.has(entity.id)) {
            deletionQueue.push({
                route: field.route,
                key: entity.id,
                entity: field.entity,
                primary: this.getPrimaryKeyData(entity)
            });
        }
    });
    
    return changes;
}
```

#### Many-to-Many Associations
```typescript
handleManyToMany(draftCollection, originCollection, deletionQueue, field, parentEntity) {
    const changes = [];
    
    // Compare collections to find additions and removals
    const draftIds = draftCollection.map(entity => entity.id);
    const originIds = originCollection.map(entity => entity.id);
    
    // Find new associations
    const newIds = draftIds.filter(id => !originIds.includes(id));
    newIds.forEach(id => {
        const entity = draftCollection.get(id);
        changes.push(this.buildManyToManyPayload(parentEntity, entity, 'create'));
    });
    
    // Find removed associations
    const removedIds = originIds.filter(id => !draftIds.includes(id));
    removedIds.forEach(id => {
        const entity = originCollection.get(id);
        deletionQueue.push(this.buildManyToManyDeletion(parentEntity, entity, field));
    });
    
    return changes;
}
```

### Deletion Queue Management

The deletion queue tracks entities that need to be removed:

```typescript
// Deletion queue structure
const deletionQueue = [
    {
        route: '/product-media',
        key: 'media-id-123',
        entity: 'product_media',
        primary: { id: 'media-id-123', productId: 'product-id-456' }
    },
    {
        route: '/product-category',
        key: 'association-key',
        entity: 'product_category',
        primary: { productId: 'product-id-456', categoryId: 'category-id-789' }
    }
];

// Deletions are processed after successful entity updates
```

### Null Value Handling

```typescript
function castValueToNullIfNecessary(value) {
    // Convert empty strings and undefined to null for API consistency
    if (value === '' || typeof value === 'undefined') {
        return null;
    }
    return value;
}

// Applied to all field values before comparison
let draftValue = castValueToNullIfNecessary(draft[fieldName]);
let originValue = castValueToNullIfNecessary(origin[fieldName]);
```

## Error Handling System

The Error Resolver maps API errors to specific entity fields and provides user-friendly error messages.

### Error Resolver Architecture

```typescript
// Located in: core/data/error-resolver.data.js
class ErrorResolver {
    constructor() {
        this.EntityDefinition = Shopware.EntityDefinition;
        this.ShopwareError = Shopware.Classes.ShopwareError;
        this.merge = Shopware.Utils.object.merge;
    }
    
    /**
     * Handle write errors from API responses
     */
    handleWriteErrors(changeset, { errors } = {}) {
        if (!errors) {
            throw new Error('[error-resolver] handleWriteError was called without errors');
        }
        
        const writeErrors = this.reduceErrorsByWriteIndex(errors);
        this.handleErrors(writeErrors, changeset);
        this.addSystemErrors(writeErrors.system);
    }
}
```

### Error Classification

#### Write Index Parsing
```typescript
reduceErrorsByWriteIndex(errors) {
    let writeErrors = { system: [] };
    
    errors.forEach((current) => {
        if (!current.source || !current.source.pointer) {
            // System-level errors without specific field reference
            writeErrors.system.push(new this.ShopwareError(current));
            return;
        }
        
        // Parse error pointer to extract write index and field
        const segments = current.source.pointer.split('/');
        
        // Remove first empty element
        if (segments[0] === '') {
            segments.shift();
        }
        
        // Extract write index (batch operation index)
        const writeIndex = segments[0];
        const fieldPath = segments.slice(1).join('.');
        
        if (!writeErrors[writeIndex]) {
            writeErrors[writeIndex] = [];
        }
        
        writeErrors[writeIndex].push({
            field: fieldPath,
            error: new this.ShopwareError(current)
        });
    });
    
    return writeErrors;
}
```

#### Error Mapping to Entities
```typescript
handleErrors(writeErrors, changeset) {
    Object.keys(writeErrors).forEach(writeIndex => {
        if (writeIndex === 'system') return;
        
        const errors = writeErrors[writeIndex];
        const entityData = this.getEntityFromChangeset(changeset, writeIndex);
        
        if (!entityData) return;
        
        errors.forEach(({ field, error }) => {
            // Map error to specific entity and field
            this.mapErrorToEntity(entityData.entity, field, error);
        });
    });
}
```

### Field-Specific Error Handling

```typescript
mapErrorToEntity(entity, fieldPath, error) {
    const segments = fieldPath.split('.');
    const fieldName = segments[0];
    
    // Store error in global error store
    Shopware.Store.get('error').addApiError({
        expression: `${entity.getEntityName()}.${entity.id}.${fieldName}`,
        error: error
    });
    
    // Handle nested field errors (associations)
    if (segments.length > 1) {
        const associationName = segments[0];
        const nestedFieldPath = segments.slice(1).join('.');
        
        if (entity[associationName]) {
            this.mapErrorToEntity(entity[associationName], nestedFieldPath, error);
        }
    }
}
```

### Delete Error Handling

```typescript
handleDeleteError(errors) {
    errors.forEach(({ error, entityName, id }) => {
        const shopwareError = new this.ShopwareError(error);
        
        // Add system-level error notification
        Shopware.Store.get('error').addSystemError({
            error: shopwareError,
        });
        
        // Add entity-specific error for UI feedback
        Shopware.Store.get('error').addApiError({
            expression: `${entityName}.${id}`,
            error: shopwareError,
        });
    });
}
```

## Error Store Integration

### Global Error State Management

```typescript
// Access error store
const errorStore = Shopware.Store.get('error');

// Reset all API errors
errorStore.resetApiErrors();

// Add system error (global notification)
errorStore.addSystemError({
    error: new Shopware.Classes.ShopwareError({
        code: 'CUSTOM_ERROR',
        detail: 'Custom error message'
    })
});

// Add field-specific error
errorStore.addApiError({
    expression: 'product.product-id-123.name',
    error: new Shopware.Classes.ShopwareError({
        code: 'VALIDATION_ERROR',
        detail: 'Product name is required'
    })
});
```

### Entity Error Retrieval

```typescript
// Get all errors for a specific entity
const entityErrors = errorStore.getApiErrorsForEntity(product);

// Structure of entityErrors:
// {
//     'name': [ShopwareError, ...],
//     'price': [ShopwareError, ...],
//     'manufacturer.name': [ShopwareError, ...]
// }

// Use in Vue components for form validation
export default {
    computed: {
        nameErrors() {
            const errors = this.errorStore.getApiErrorsForEntity(this.product);
            return errors.name || [];
        },
        
        hasErrors() {
            const errors = this.errorStore.getApiErrorsForEntity(this.product);
            return Object.keys(errors).length > 0;
        }
    },
    
    methods: {
        getFieldError(fieldName) {
            const errors = this.errorStore.getApiErrorsForEntity(this.product);
            return errors[fieldName] ? errors[fieldName][0].detail : null;
        }
    }
};
```

## Practical Usage Examples

### Save Operation with Error Handling

```typescript
async saveProduct(product) {
    try {
        // Clear previous errors
        Shopware.Store.get('error').resetApiErrors();
        
        // Attempt to save
        await this.productRepository.save(product, this.context);
        
        // Success feedback
        this.showSuccessNotification('Product saved successfully');
        
    } catch (error) {
        // Errors are automatically processed by ErrorResolver
        // and stored in the error store
        
        // Get field-specific errors for UI display
        const fieldErrors = Shopware.Store.get('error').getApiErrorsForEntity(product);
        
        if (Object.keys(fieldErrors).length > 0) {
            this.showValidationErrors(fieldErrors);
        } else {
            // System error
            this.showErrorNotification('Failed to save product');
        }
    }
}

showValidationErrors(fieldErrors) {
    Object.keys(fieldErrors).forEach(fieldName => {
        const errors = fieldErrors[fieldName];
        errors.forEach(error => {
            console.error(`${fieldName}: ${error.detail}`);
        });
    });
}
```

### Bulk Operation Error Handling

```typescript
async saveBulkProducts(products) {
    try {
        // Use sync service for bulk operations
        const syncService = Shopware.Service('syncService');
        
        const operations = products.map(product => ({
            action: 'upsert',
            entity: 'product',
            payload: [product]
        }));
        
        await syncService.sync(operations);
        
    } catch (error) {
        // Bulk operation errors include write indices
        if (error.response?.data?.errors) {
            const errors = error.response.data.errors;
            
            // Group errors by write index (batch position)
            const errorsByIndex = {};
            errors.forEach(err => {
                if (err.source?.pointer) {
                    const writeIndex = err.source.pointer.split('/')[1];
                    if (!errorsByIndex[writeIndex]) {
                        errorsByIndex[writeIndex] = [];
                    }
                    errorsByIndex[writeIndex].push(err);
                }
            });
            
            // Map errors back to specific products
            Object.keys(errorsByIndex).forEach(index => {
                const productIndex = parseInt(index);
                const product = products[productIndex];
                const productErrors = errorsByIndex[index];
                
                console.error(`Product ${product.name} errors:`, productErrors);
            });
        }
    }
}
```

### Form Validation Integration

```vue
<template>
    <div>
        <sw-field
            v-model="product.name"
            :error="getFieldError('name')"
            :disabled="isLoading"
            label="Product Name"
            required
        />
        
        <sw-field
            v-model="product.price"
            :error="getFieldError('price')"
            :disabled="isLoading"
            label="Price"
            type="number"
        />
        
        <sw-button
            :disabled="hasErrors || isLoading"
            @click="saveProduct"
        >
            Save Product
        </sw-button>
    </div>
</template>

<script>
export default {
    inject: ['repositoryFactory'],
    
    data() {
        return {
            product: null,
            isLoading: false
        };
    },
    
    computed: {
        errorStore() {
            return Shopware.Store.get('error');
        },
        
        hasErrors() {
            if (!this.product) return false;
            const errors = this.errorStore.getApiErrorsForEntity(this.product);
            return Object.keys(errors).length > 0;
        }
    },
    
    methods: {
        getFieldError(fieldName) {
            if (!this.product) return null;
            
            const errors = this.errorStore.getApiErrorsForEntity(this.product);
            const fieldErrors = errors[fieldName];
            
            return fieldErrors && fieldErrors.length > 0 ? 
                { message: fieldErrors[0].detail } : null;
        },
        
        async saveProduct() {
            this.isLoading = true;
            
            try {
                this.errorStore.resetApiErrors();
                await this.productRepository.save(this.product, this.context);
                this.$emit('save-success');
            } catch (error) {
                // Errors automatically handled by ErrorResolver
                this.$emit('save-error', error);
            } finally {
                this.isLoading = false;
            }
        }
    }
};
</script>
```

## Performance Considerations

### Changeset Optimization

```typescript
// ✅ Good: Batch multiple changes before save
product.name = 'New Name';
product.description = 'New Description';
product.active = true;
await productRepository.save(product, context); // Single changeset generation

// ❌ Avoid: Multiple saves for related changes
product.name = 'New Name';
await productRepository.save(product, context); // Changeset 1
product.description = 'New Description';
await productRepository.save(product, context); // Changeset 2
```

### Error Handling Performance

```typescript
// ✅ Good: Reset errors strategically
// Reset only before operations that might generate new errors
errorStore.resetApiErrors();
await repository.save(entity, context);

// ❌ Avoid: Excessive error store clearing
// Don't reset errors on every component update
```

## Best Practices

### Change Tracking
1. **Let the system handle change detection automatically**
2. **Batch related changes before saving**
3. **Use entity.reset() to discard changes**
4. **Monitor entity.isModified() for UI state**

### Error Handling
1. **Always handle save/delete operation errors**
2. **Provide field-specific error feedback**
3. **Clear errors before retry operations**
4. **Use system errors for global notifications**

### Performance
1. **Minimize entity cloning and copying**
2. **Batch operations when possible**
3. **Clear error stores when appropriate**
4. **Use error store selectively for UI binding**
