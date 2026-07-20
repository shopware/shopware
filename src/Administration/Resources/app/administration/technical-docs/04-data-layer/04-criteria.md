# Criteria API

The Criteria API provides a fluent, type-safe interface for expressing complex queries in Shopware 6 Administration. It translates query intentions into backend DAL search parameters, enabling powerful filtering, sorting, pagination, and aggregation capabilities.

## Overview

The Criteria class is imported from the Meteor Admin SDK and serves as the primary query builder for repository operations:

```typescript
// Located in: core/data/criteria.data.ts
import Criteria from '@shopware-ag/meteor-admin-sdk/es/data/Criteria';
```

## Basic Usage

### Creating Criteria

```typescript
// Basic criteria with pagination
const criteria = new Criteria(1, 25); // page 1, 25 items per page

// Criteria without pagination (fetch all)
const criteria = new Criteria();

// Set pagination after creation
criteria.setPage(2);
criteria.setLimit(50);
criteria.setTotalCountMode(1); // Enable total count
```

### Repository Integration

```typescript
const productRepository = this.repositoryFactory.create('product');
const criteria = new Criteria(1, 25);

// Use criteria in search operations
const searchResult = await productRepository.search(criteria, context);
const products = searchResult.data;
const totalCount = searchResult.total;
```

## Filtering

The Criteria API supports various filter types for precise data selection:

### Basic Filters

```typescript
const criteria = new Criteria();

// Equals filter
criteria.addFilter(Criteria.equals('active', true));
criteria.addFilter(Criteria.equals('manufacturerId', 'manufacturer-id'));

// Not equals filter
criteria.addFilter(Criteria.not('equals', 'active', false));

// Multiple value filter (IN)
criteria.addFilter(Criteria.equalsAny('id', ['id-1', 'id-2', 'id-3']));

// Null/Not null filters
criteria.addFilter(Criteria.equals('description', null));
criteria.addFilter(Criteria.not('equals', 'description', null));
```

### Range Filters

```typescript
// Numeric ranges
criteria.addFilter(Criteria.range('price', {
    gte: 10.00,  // greater than or equal
    lte: 100.00  // less than or equal
}));

// Date ranges
criteria.addFilter(Criteria.range('createdAt', {
    gte: '2023-01-01 00:00:00',
    lte: '2023-12-31 23:59:59'
}));

// Single-sided ranges
criteria.addFilter(Criteria.range('stock', { gte: 1 })); // stock >= 1
criteria.addFilter(Criteria.range('weight', { lt: 5.0 })); // weight < 5.0
```

### Text Search Filters

```typescript
// Contains filter (case-insensitive substring)
criteria.addFilter(Criteria.contains('name', 'searchterm'));

// Prefix filter (starts with)
criteria.addFilter(Criteria.prefix('productNumber', 'SW-'));

// Suffix filter (ends with)
criteria.addFilter(Criteria.suffix('name', '-premium'));

// Full-text search
criteria.setTerm('searchterm'); // Searches across indexed fields
```

### Complex Logical Filters

```typescript
// Multi filter (AND logic)
criteria.addFilter(Criteria.multi('AND', [
    Criteria.equals('active', true),
    Criteria.range('stock', { gte: 1 }),
    Criteria.contains('name', 'premium')
]));

// Multi filter (OR logic)
criteria.addFilter(Criteria.multi('OR', [
    Criteria.equals('categoryId', 'category-1'),
    Criteria.equals('categoryId', 'category-2')
]));

// Nested logical filters
criteria.addFilter(Criteria.multi('AND', [
    Criteria.equals('active', true),
    Criteria.multi('OR', [
        Criteria.contains('name', 'smartphone'),
        Criteria.contains('name', 'tablet')
    ])
]));
```

### Association Filters

```typescript
// Filter by associated entity properties
criteria.addFilter(Criteria.equals('manufacturer.name', 'Apple'));
criteria.addFilter(Criteria.contains('categories.name', 'Electronics'));

// Exists filter (has association)
criteria.addFilter(Criteria.not('equals', 'manufacturer', null));

// Count filter (association count)
criteria.addFilter(Criteria.range('categories.id', { gte: 1 }));
```

## Sorting

Define how search results should be ordered:

```typescript
const criteria = new Criteria();

// Basic sorting
criteria.addSorting(Criteria.sort('name', 'ASC'));
criteria.addSorting(Criteria.sort('createdAt', 'DESC'));

// Natural sorting (alphanumeric) - third parameter enables natural sorting
criteria.addSorting(Criteria.sort('productNumber', 'ASC', true));
criteria.addSorting(Criteria.sort('config.customFieldPosition', 'ASC', true));

// Multiple sort fields (priority order)
criteria.addSorting(Criteria.sort('active', 'DESC'));     // First: active items
criteria.addSorting(Criteria.sort('name', 'ASC'));       // Then: alphabetical
criteria.addSorting(Criteria.sort('createdAt', 'DESC')); // Finally: newest first

// Sort by association fields
criteria.addSorting(Criteria.sort('manufacturer.name', 'ASC'));
criteria.addSorting(Criteria.sort('customFieldSet.name', 'ASC'));

// Alternative natural sorting method
criteria.addSorting(Criteria.naturalSorting('config.customFieldPosition'));
```

## Associations

Load related entities efficiently to avoid N+1 query problems:

### Basic Associations

```typescript
const criteria = new Criteria();

// Load simple associations
criteria.addAssociation('manufacturer');
criteria.addAssociation('categories');
criteria.addAssociation('media');

// Access loaded associations
const products = await productRepository.search(criteria, context);
products.forEach(product => {
    console.log(product.manufacturer.name);    // Direct access
    console.log(product.categories.length);    // Collection access
});
```

### Nested Associations

```typescript
// Load nested associations (dot notation)
criteria.addAssociation('categories.media');
criteria.addAssociation('manufacturer.media.thumbnails');

// Access nested data
const product = products.first();
product.categories.forEach(category => {
    console.log(category.media.length); // Media loaded per category
});
```

### Association Criteria

Filter and sort associations with dedicated criteria:

```typescript
// Create association criteria
const categoryCriteria = new Criteria();
categoryCriteria.addFilter(Criteria.equals('active', true));
categoryCriteria.addSorting(Criteria.sort('position', 'ASC'));

// Apply to association
criteria.addAssociation('categories', categoryCriteria);

// Nested association criteria
const mediaCriteria = new Criteria();
mediaCriteria.addSorting(Criteria.sort('position', 'ASC'));
mediaCriteria.setLimit(5); // Limit media per category

criteria.getAssociation('categories').addAssociation('media', mediaCriteria);
```

### Association Methods

```typescript
// Get association criteria for modification
const categoryAssoc = criteria.addAssociation('categories');
categoryAssoc.addFilter(Criteria.equals('type', 'page'));
categoryAssoc.addSorting(Criteria.sort('name', 'ASC'));

// Check if association exists
if (criteria.hasAssociation('manufacturer')) {
    criteria.getAssociation('manufacturer').addAssociation('country');
}

// Remove association
criteria.removeAssociation('media');
```

## Aggregations

Perform statistical operations on search results:

### Basic Aggregations

```typescript
const criteria = new Criteria();

// Count aggregations
criteria.addAggregation(Criteria.count('productCount', 'id'));
criteria.addAggregation(Criteria.count('activeProductCount', 'id')
    .addFilter(Criteria.equals('active', true)));

// Sum aggregations
criteria.addAggregation(Criteria.sum('totalStock', 'stock'));
criteria.addAggregation(Criteria.sum('totalPrice', 'price'));

// Average aggregations
criteria.addAggregation(Criteria.avg('averagePrice', 'price'));
criteria.addAggregation(Criteria.avg('averageStock', 'stock'));

// Min/Max aggregations
criteria.addAggregation(Criteria.min('minPrice', 'price'));
criteria.addAggregation(Criteria.max('maxPrice', 'price'));
```

### Terms Aggregations

```typescript
// Group by field values
criteria.addAggregation(Criteria.terms('manufacturerGroups', 'manufacturerId', null, null, null));
criteria.addAggregation(Criteria.terms('categoryGroups', 'categories.id', null, null, null));

// Terms aggregation with sorting and limiting
criteria.addAggregation(
    Criteria.terms('topManufacturers', 'manufacturerId', 10, 
        Criteria.sort('_count', 'DESC'))
);
```

### Date Histogram Aggregations

```typescript
// Group by date intervals
criteria.addAggregation(
    Criteria.histogram('createdByMonth', 'createdAt', 'month', 'Y-m')
);

criteria.addAggregation(
    Criteria.histogram('createdByDay', 'createdAt', 'day', 'Y-m-d')
);
```

### Accessing Aggregation Results

```typescript
const searchResult = await productRepository.search(criteria, context);

// Access aggregation results
const aggregations = searchResult.aggregations;

console.log('Total products:', aggregations.productCount.count);
console.log('Average price:', aggregations.averagePrice.avg);

// Terms aggregation results
aggregations.manufacturerGroups.buckets.forEach(bucket => {
    console.log(`Manufacturer ${bucket.key}: ${bucket.count} products`);
});

// Date histogram results
aggregations.createdByMonth.buckets.forEach(bucket => {
    console.log(`${bucket.key}: ${bucket.count} products created`);
});
```

## Pagination and Total Count

### Pagination Configuration

```typescript
const criteria = new Criteria();

// Set page and limit
criteria.setPage(2);        // Page number (1-based)
criteria.setLimit(25);      // Items per page

// Or use constructor
const criteria = new Criteria(2, 25);

// Calculate offset manually
const offset = (page - 1) * limit;
criteria.setOffset(offset);
criteria.setLimit(limit);
```

### Total Count Modes

```typescript
// Total count modes for performance optimization
criteria.setTotalCountMode(0); // No total count (fastest)
criteria.setTotalCountMode(1); // Exact total count (default)
criteria.setTotalCountMode(2); // Approximate total count (for very large datasets)

// Check total count in results
const searchResult = await productRepository.search(criteria, context);
console.log('Total available items:', searchResult.total);
console.log('Current page items:', searchResult.data.length);
```

## Advanced Features

### Custom Queries with Title

```typescript
// Set query title for debugging/logging
criteria.setTitle('Product Search - Active Premium Products');

// Useful for API monitoring and debugging
const searchResult = await productRepository.search(criteria, context);
```

### IDs-Only Search

```typescript
// Search for IDs only (performance optimization)
const idResult = await productRepository.searchIds(criteria, context);
console.log('Found IDs:', idResult.data);        // Array of IDs
console.log('Total count:', idResult.total);     // Total matching records
```

### Criteria Parsing

```typescript
// Convert criteria to API payload (usually automatic)
const parsedCriteria = criteria.parse();
console.log('API payload:', parsedCriteria);

// Useful for debugging or custom API calls
const response = await httpClient.post('/search/product', parsedCriteria);
```

## Performance Best Practices

### Efficient Filtering

```typescript
// ✅ Good: Use specific filters
criteria.addFilter(Criteria.equals('active', true));
criteria.addFilter(Criteria.equalsAny('categoryId', ['cat1', 'cat2']));

// ❌ Avoid: Overly broad contains filters
criteria.addFilter(Criteria.contains('description', 'a')); // Too broad
```

### Smart Association Loading

```typescript
// ✅ Good: Load only needed associations
criteria.addAssociation('manufacturer');

// ❌ Avoid: Loading unnecessary nested associations
criteria.addAssociation('categories.products.manufacturer.country'); // Too deep
```

### Pagination Strategy

```typescript
// ✅ Good: Reasonable page sizes
const criteria = new Criteria(1, 25);

// ❌ Avoid: Very large page sizes
const criteria = new Criteria(1, 1000); // Too large

// ✅ Good: Disable total count when not needed
criteria.setTotalCountMode(0); // Skip total count calculation
```

### Aggregation Optimization

```typescript
// ✅ Good: Limit aggregation results
criteria.addAggregation(
    Criteria.terms('topCategories', 'categoryId', 10) // Limit to top 10
);

// ❌ Avoid: Unlimited aggregations on large datasets
criteria.addAggregation(
    Criteria.terms('allCategories', 'categoryId') // No limit
);
```

## Common Patterns

### Search with Fallback

```typescript
async function searchProducts(searchTerm, fallbackCriteria) {
    // Primary search with term
    const criteria = new Criteria(1, 25);
    criteria.setTerm(searchTerm);
    
    let result = await productRepository.search(criteria, context);
    
    // Fallback to broader criteria if no results
    if (result.total === 0 && fallbackCriteria) {
        result = await productRepository.search(fallbackCriteria, context);
    }
    
    return result;
}
```

### Dynamic Filter Building

```typescript
function buildProductCriteria(filters = {}) {
    const criteria = new Criteria(1, 25);
    
    if (filters.active !== undefined) {
        criteria.addFilter(Criteria.equals('active', filters.active));
    }
    
    if (filters.manufacturerId) {
        criteria.addFilter(Criteria.equals('manufacturerId', filters.manufacturerId));
    }
    
    if (filters.priceRange) {
        criteria.addFilter(Criteria.range('price', filters.priceRange));
    }
    
    if (filters.searchTerm) {
        criteria.setTerm(filters.searchTerm);
    }
    
    return criteria;
}
```

### Reusable Association Patterns

```typescript
// Common association patterns for different entity types
const PRODUCT_ASSOCIATIONS = {
    basic: ['manufacturer', 'categories'],
    detailed: ['manufacturer', 'categories', 'media', 'prices'],
    full: ['manufacturer', 'categories', 'media', 'prices', 'properties', 'options']
};

function createProductCriteria(associationLevel = 'basic') {
    const criteria = new Criteria();
    
    PRODUCT_ASSOCIATIONS[associationLevel].forEach(association => {
        criteria.addAssociation(association);
    });
    
    return criteria;
}
```
