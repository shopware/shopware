# Entity Definitions and Schema

Entity Definitions in Shopware 6 Administration define the structure, relationships, and validation rules for entities. They serve as the contract between the frontend and backend, enabling type safety, automatic validation, and intelligent code completion.

## Entity Definition Structure

### Core Definition Class

Entity definitions are managed through a centralized registry system:

```typescript
// Located in: core/factory/entity-definition.factory.js
// and core/data/entity-definition.data.ts

// Check if definition exists
if (Shopware.EntityDefinition.has('product')) {
    const definition = Shopware.EntityDefinition.get('product');
}

// Add new definition
Shopware.EntityDefinition.add('custom_entity', {
    entity: 'custom_entity',
    properties: {
        id: { type: 'uuid', flags: { primary_key: true, required: true } },
        name: { type: 'string', flags: { required: true } },
        active: { type: 'boolean' }
    }
});
```

### Property Types

Entity definitions support various field types that map to backend DAL types:

```typescript
const scalarTypes = [
    'uuid',      // UUID strings
    'int',       // Integers
    'text',      // Long text content
    'password',  // Password fields (masked)
    'float',     // Floating point numbers
    'string',    // Short strings
    'blob',      // Binary data
    'boolean',   // True/false values
    'date'       // Date/datetime values
];

const jsonTypes = [
    'json_list',   // JSON arrays
    'json_object'  // JSON objects
];
```

### Property Flags

Property flags control behavior and validation:

```typescript
const productDefinition = {
    entity: 'product',
    properties: {
        id: {
            type: 'uuid',
            flags: {
                primary_key: true,    // Primary key field
                required: true,       // Cannot be null
                read_only: true      // Cannot be modified after creation
            }
        },
        name: {
            type: 'string',
            flags: {
                required: true,       // Required field
                translatable: true,   // Multi-language support
                search_ranking: 500   // Search relevance weight
            }
        },
        active: {
            type: 'boolean',
            flags: {
                write_protected: ['system'] // Protected in certain contexts
            }
        },
        price: {
            type: 'float',
            flags: {
                extension: true       // Custom field extension
            }
        }
    }
};
```

## Association Definitions

### Association Types

Entity definitions support four types of associations:

```typescript
// One-to-Many: Product has many media items
media: {
    type: 'association',
    relation: 'one_to_many',
    entity: 'product_media',
    flags: {
        cascade_delete: true  // Delete associated records
    }
}

// Many-to-One: Product belongs to one manufacturer
manufacturer: {
    type: 'association',
    relation: 'many_to_one',
    entity: 'product_manufacturer',
    flags: {
        required: false
    }
}

// Many-to-Many: Product belongs to many categories
categories: {
    type: 'association',
    relation: 'many_to_many',
    entity: 'category',
    flags: {
        cascade_delete: false
    }
}

// One-to-One: Product has one default price
price: {
    type: 'association',
    relation: 'one_to_one',
    entity: 'product_price',
    flags: {
        required: true
    }
}
```

### Complex Association Examples

```typescript
const complexProductDefinition = {
    entity: 'product',
    properties: {
        // Basic fields
        id: { type: 'uuid', flags: { primary_key: true, required: true } },
        productNumber: { type: 'string', flags: { required: true } },
        name: { type: 'string', flags: { required: true, translatable: true } },
        
        // Foreign key relationships
        manufacturerId: { type: 'uuid' },
        taxId: { type: 'uuid', flags: { required: true } },
        
        // Association definitions
        manufacturer: {
            type: 'association',
            relation: 'many_to_one',
            entity: 'product_manufacturer',
            localField: 'manufacturerId',
            referenceField: 'id'
        },
        
        tax: {
            type: 'association',
            relation: 'many_to_one',
            entity: 'tax',
            localField: 'taxId',
            referenceField: 'id',
            flags: { required: true }
        },
        
        prices: {
            type: 'association',
            relation: 'one_to_many',
            entity: 'product_price',
            localField: 'id',
            referenceField: 'productId',
            flags: { cascade_delete: true }
        },
        
        categories: {
            type: 'association',
            relation: 'many_to_many',
            entity: 'category',
            mappingEntity: 'product_category',
            localField: 'id',
            referenceField: 'id',
            mappingLocalField: 'productId',
            mappingReferenceField: 'categoryId'
        }
    }
};
```

## Definition Registry Operations

### Registry Management

```typescript
// Get definition registry
const registry = Shopware.EntityDefinition.getDefinitionRegistry();

// Check if definition exists
if (Shopware.EntityDefinition.has('product')) {
    console.log('Product definition exists');
}

// Get definition
const productDefinition = Shopware.EntityDefinition.get('product');

// Add new definition
Shopware.EntityDefinition.add('custom_entity', definitionSchema);

// Remove definition
Shopware.EntityDefinition.remove('custom_entity');
```

### Definition Querying

```typescript
const definition = Shopware.EntityDefinition.get('product');

// Get primary key fields
const primaryKeys = definition.getPrimaryKeyFields();
console.log('Primary keys:', Object.keys(primaryKeys));

// Get association fields
const associations = definition.getAssociationFields();
console.log('Associations:', Object.keys(associations));

// Get required fields
const required = definition.getRequiredFields();
console.log('Required fields:', Object.keys(required));

// Get translatable fields
const translatable = Shopware.EntityDefinition.getTranslatedFields('product');
console.log('Translatable fields:', Object.keys(translatable));
```

### Field Type Checking

```typescript
const definition = Shopware.EntityDefinition.get('product');

// Check field types
definition.forEachField((field, fieldName) => {
    if (definition.isScalarField(field)) {
        console.log(`${fieldName} is a scalar field`);
    }
    
    if (definition.isJsonField(field)) {
        console.log(`${fieldName} is a JSON field`);
    }
    
    if (field.type === 'association') {
        console.log(`${fieldName} is an association`);
        console.log(`Relation type: ${field.relation}`);
        console.log(`Target entity: ${field.entity}`);
    }
});
```

## Custom Entity Definitions

### Creating Custom Entities

```typescript
// Define a custom entity schema
const customEntitySchema = {
    entity: 'blog_post',
    properties: {
        id: {
            type: 'uuid',
            flags: { primary_key: true, required: true }
        },
        title: {
            type: 'string',
            flags: { required: true, translatable: true }
        },
        content: {
            type: 'text',
            flags: { translatable: true }
        },
        published: {
            type: 'boolean',
            flags: { required: true }
        },
        publishedAt: {
            type: 'date'
        },
        authorId: {
            type: 'uuid',
            flags: { required: true }
        },
        categoryId: {
            type: 'uuid'
        },
        
        // Associations
        author: {
            type: 'association',
            relation: 'many_to_one',
            entity: 'user',
            localField: 'authorId',
            referenceField: 'id'
        },
        
        category: {
            type: 'association',
            relation: 'many_to_one',
            entity: 'blog_category',
            localField: 'categoryId',
            referenceField: 'id'
        },
        
        tags: {
            type: 'association',
            relation: 'many_to_many',
            entity: 'blog_tag',
            mappingEntity: 'blog_post_tag'
        }
    }
};

// Register the custom entity
Shopware.EntityDefinition.add('blog_post', customEntitySchema);

// Now you can use it with repositories
const blogPostRepository = this.repositoryFactory.create('blog_post');
```

### Custom Field Extensions

```typescript
// Extend existing entity with custom fields
const productExtension = {
    customFields: {
        type: 'json_object',
        flags: {
            extension: true,
            translatable: false
        }
    },
    
    // Add custom association
    relatedProducts: {
        type: 'association',
        relation: 'many_to_many',
        entity: 'product',
        mappingEntity: 'product_cross_selling',
        flags: {
            extension: true
        }
    }
};

// This would typically be done through the extension system
// Rather than directly modifying core definitions
```

## Schema Validation

### Built-in Validation

Entity definitions provide automatic validation based on field properties:

```typescript
// Validation occurs automatically during entity operations
const product = productRepository.create(context);

// Required field validation
product.name = null; // Will cause validation error on save

// Type validation (handled by TypeScript and runtime)
product.active = 'invalid'; // Type error

// Custom validation can be added through the entity definition
const validatedDefinition = {
    entity: 'validated_entity',
    properties: {
        email: {
            type: 'string',
            flags: { 
                required: true,
                validation: {
                    pattern: '^[^@]+@[^@]+\.[^@]+$',
                    message: 'Invalid email format'
                }
            }
        },
        age: {
            type: 'int',
            flags: {
                validation: {
                    min: 0,
                    max: 150,
                    message: 'Age must be between 0 and 150'
                }
            }
        }
    }
};
```

### Custom Validation Rules

```typescript
// Custom validation can be implemented through decorators
Shopware.EntityDefinition.registerValidator('product', (entity, definition) => {
    const errors = [];
    
    // Custom business rules
    if (entity.price < 0) {
        errors.push({
            field: 'price',
            message: 'Price cannot be negative'
        });
    }
    
    if (entity.stock < 0) {
        errors.push({
            field: 'stock',
            message: 'Stock cannot be negative'
        });
    }
    
    // Cross-field validation
    if (entity.active && !entity.name) {
        errors.push({
            field: 'name',
            message: 'Active products must have a name'
        });
    }
    
    return errors;
});
```

## TypeScript Integration

### Type Generation

Entity definitions are used to generate TypeScript types:

```typescript
// Generated types based on entity definitions
declare global {
    namespace EntitySchema {
        interface Entities {
            product: {
                id: string;
                productNumber: string;
                name: string;
                description?: string;
                active: boolean;
                price: number;
                stock: number;
                manufacturerId?: string;
                
                // Associations
                manufacturer?: Entity<'product_manufacturer'>;
                categories: EntityCollection<'category'>;
                media: EntityCollection<'product_media'>;
            };
            
            blog_post: {
                id: string;
                title: string;
                content?: string;
                published: boolean;
                publishedAt?: Date;
                authorId: string;
                categoryId?: string;
                
                // Associations
                author: Entity<'user'>;
                category?: Entity<'blog_category'>;
                tags: EntityCollection<'blog_tag'>;
            };
        }
    }
}
```

### Type-Safe Repository Usage

```typescript
// TypeScript ensures type safety based on entity definitions
const productRepository: Repository<'product'> = this.repositoryFactory.create('product');

// Type-safe entity creation and manipulation
const product: Entity<'product'> = productRepository.create(context);
product.name = "Test Product";    // ✅ Valid
product.price = 29.99;           // ✅ Valid
product.invalid = "value";       // ❌ TypeScript error

// Type-safe search results
const products: EntityCollection<'product'> = await productRepository.search(criteria, context);
products.forEach((product: Entity<'product'>) => {
    console.log(product.name);   // ✅ Type-safe access
});
```

## Performance Considerations

### Definition Loading

```typescript
// Definitions are loaded once and cached
// Avoid repeatedly accessing definitions in loops
const definition = Shopware.EntityDefinition.get('product'); // Cache this

// ✅ Good: Cache definition reference
const processProducts = (products) => {
    const definition = Shopware.EntityDefinition.get('product');
    
    products.forEach(product => {
        // Use cached definition
        const fields = definition.getAssociationFields();
    });
};

// ❌ Avoid: Repeated definition lookups
const processProductsBad = (products) => {
    products.forEach(product => {
        // Repeated lookup - inefficient
        const definition = Shopware.EntityDefinition.get('product');
        const fields = definition.getAssociationFields();
    });
};
```

### Memory Usage

```typescript
// Definitions are shared across all instances
// No need to store multiple copies
export default {
    data() {
        return {
            // ✅ Good: Store entity instances, not definitions
            product: null,
            products: null
        };
    },
    
    computed: {
        // ✅ Good: Access definition when needed
        productDefinition() {
            return Shopware.EntityDefinition.get('product');
        }
    }
};
```

## Best Practices

### Definition Design

1. **Use clear, descriptive property names**
2. **Set appropriate flags for validation and behavior**
3. **Document complex associations with comments**
4. **Use consistent naming conventions**

### Association Management

1. **Define associations bidirectionally when appropriate**
2. **Use cascade delete carefully**
3. **Consider performance impact of deep associations**
4. **Validate association integrity**

### Extension Strategy

1. **Use extensions for custom fields**
2. **Avoid modifying core definitions directly**
3. **Document custom definitions thoroughly**
4. **Test custom definitions extensively**

### Type Safety

1. **Leverage TypeScript for compile-time checking**
2. **Use generated types consistently**
3. **Validate runtime data against definitions**
4. **Handle type mismatches gracefully**
