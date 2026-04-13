# Change Tracking and Error Handling

Shopware 6 Administration provides change tracking through the Changeset Generator and comprehensive error handling through the Error Resolver. These systems ensure data integrity, optimize API performance, and provide meaningful feedback to users.

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

The Error Resolver maps API errors to specific entity fields and provides user-friendly error messages. However, in practice, error handling is largely automated through repository operations and helper functions.

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
     * This is called automatically by repository operations
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

### Automatic Error Handling by Repositories

**Important**: Repository operations automatically handle error processing and reset API errors. Manual error store manipulation is rarely needed in practice.

```typescript
// ✅ Repositories automatically handle error processing
async saveCustomerGroup() {
    this.isLoading = true;
    
    try {
        // Repository automatically:
        // 1. Resets previous API errors for this entity
        // 2. Processes any validation errors from the API response
        // 3. Maps errors to specific fields using the ErrorResolver
        await this.customerGroupRepository.save(this.customerGroup);
        
        this.isSaveSuccessful = true;
    } catch (error) {
        // Errors are already processed and stored
        // No manual error handling needed for field-specific errors
        this.createNotificationError({
            message: this.$tc('sw-settings-customer-group.detail.notificationErrorMessage'),
        });
    } finally {
        this.isLoading = false;
    }
}
```

### Field Error Mapping with mapPropertyErrors

The most common pattern for handling field errors in Vue components is using the `mapPropertyErrors` helper:

```javascript
// Component import
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

export default {
    // Map errors for specific entity fields
    computed: {
        ...mapPropertyErrors('customerGroup', ['name']),
        // This creates computed properties like:
        // customerGroupNameError: returns error for customerGroup.name field
        
        // For multiple fields:
        ...mapPropertyErrors('product', [
            'name',
            'productNumber', 
            'price',
            'taxId'
        ]),
        // Creates: productNameError, productProductNumberError, etc.
    }
};
```

### Real-World Usage Patterns

#### Basic Entity Save with Error Handling
```vue
<template>
    <div>
        <sw-field
            v-model="customerGroup.name"
            :error="customerGroupNameError"
            :disabled="isLoading"
            label="Customer Group Name"
            required
        />
        
        <sw-button
            :disabled="isLoading"
            @click="onSave"
        >
            Save Customer Group
        </sw-button>
    </div>
</template>

<script>
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

export default {
    inject: ['repositoryFactory'],
    
    data() {
        return {
            customerGroup: null,
            isLoading: false,
            isSaveSuccessful: false
        };
    },
    
    computed: {
        customerGroupRepository() {
            return this.repositoryFactory.create('customer_group');
        },
        
        // Automatically maps field errors from the error store
        ...mapPropertyErrors('customerGroup', ['name']),
    },
    
    methods: {
        async onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            try {
                // Repository automatically handles:
                // - Error store cleanup for this entity
                // - Changeset generation
                // - API error processing and mapping
                await this.customerGroupRepository.save(this.customerGroup);
                
                this.isSaveSuccessful = true;
            } catch (error) {
                // Field-specific errors are already processed and available
                // via mapPropertyErrors computed properties
                this.createNotificationError({
                    message: this.$tc('sw-settings-customer-group.detail.notificationErrorMessage'),
                });
            } finally {
                this.isLoading = false;
            }
        }
    }
};
</script>
```

#### Advanced Error Handling for Complex Forms
```javascript
const { mapPropertyErrors } = Shopware.Component.getComponentHelper();

export default {
    computed: {
        // Map errors for main entity
        ...mapPropertyErrors('product', [
            'name',
            'productNumber',
            'price',
            'taxId',
            'manufacturerId'
        ]),
        
        // Map errors for associated entities
        ...mapPropertyErrors('manufacturer', ['name']),
    },
    
    methods: {
        async saveProduct() {
            try {
                // Save main product - errors are handled automatically
                await this.productRepository.save(this.product);
                
                // Save associated manufacturer if modified
                if (this.manufacturer && this.manufacturerRepository.hasChanges(this.manufacturer)) {
                    await this.manufacturerRepository.save(this.manufacturer);
                }
                
                this.isSaveSuccessful = true;
                
            } catch (error) {
                // Handle specific error codes if needed
                const errorCode = error.response?.data?.errors?.[0]?.code;
                
                if (errorCode === 'CONTENT__DUPLICATE_PRODUCT_NUMBER') {
                    this.createNotificationError({
                        message: this.$t('sw-product.notification.duplicateProductNumber'),
                    });
                    return;
                }
                
                // Generic error handling
                this.createNotificationError({
                    message: this.$tc('global.notification.notificationSaveErrorMessage'),
                });
            }
        }
    }
};
```

### Error Classification

#### Automatic Write Index Parsing
```typescript
// This happens automatically in the ErrorResolver
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
        
        if (segments[0] === '') {
            segments.shift();
        }
        
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

#### Automatic Error Mapping to Entities
```typescript
// Automatically called by repository operations
handleErrors(writeErrors, changeset) {
    Object.keys(writeErrors).forEach(writeIndex => {
        if (writeIndex === 'system') return;
        
        const errors = writeErrors[writeIndex];
        const entityData = this.getEntityFromChangeset(changeset, writeIndex);
        
        if (!entityData) return;
        
        errors.forEach(({ field, error }) => {
            // Automatically map error to specific entity and field
            this.mapErrorToEntity(entityData.entity, field, error);
        });
    });
}
```

### Manual Error Store Access (Rarely Needed)

In most cases, you don't need to manually interact with the error store. However, if needed:

```typescript
// Only use when repository patterns don't apply
const errorStore = Shopware.Store.get('error');

// Manual error reset (usually unnecessary)
errorStore.resetApiErrors();

// Manual error retrieval (prefer mapPropertyErrors)
const entityErrors = errorStore.getApiErrorsForEntity(product);
```

## Practical Usage Examples

### Standard Save Operation
```typescript
async saveEntity() {
    try {
        // ✅ Repository handles all error processing automatically
        await this.repository.save(this.entity);
        
        this.showSuccessNotification();
        
    } catch (error) {
        // ✅ Field errors are already mapped and available via mapPropertyErrors
        // Only handle system-level errors or specific error codes here
        this.handleSaveError(error);
    }
}
```

### Bulk Operation Error Handling
```typescript
async saveBulkProducts(products) {
    try {
        const syncService = Shopware.Service('syncService');
        
        const operations = products.map(product => ({
            action: 'upsert',
            entity: 'product',
            payload: [product]
        }));
        
        await syncService.sync(operations);
        
    } catch (error) {
        // Bulk operation errors are automatically processed
        // Field-specific errors are available via mapPropertyErrors
        if (error.response?.data?.errors) {
            const errors = error.response.data.errors;
            
            // Handle specific error patterns if needed
            this.processBulkErrors(errors);
        }
    }
}
```

## Performance Considerations

### Repository Optimization

```typescript
// ✅ Good: Repository automatically manages error state
await this.repository.save(entity); // Handles errors efficiently

// ❌ Unnecessary: Manual error store manipulation
// errorStore.resetApiErrors(); // Repository does this automatically
// await this.repository.save(entity);
```

### Error Handling Performance

```typescript
// ✅ Good: Use mapPropertyErrors for automatic field error mapping
computed: {
    ...mapPropertyErrors('product', ['name', 'price']),
}

// ❌ Avoid: Manual error store access in computed properties
computed: {
    nameError() {
        // This is less efficient than mapPropertyErrors
        const errors = this.errorStore.getApiErrorsForEntity(this.product);
        return errors.name ? errors.name[0] : null;
    }
}
```