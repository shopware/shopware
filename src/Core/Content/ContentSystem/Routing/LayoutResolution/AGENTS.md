# LayoutResolution

@README.md

## Source Code References

- `LayoutResolver` - Priority-based assignment matching (main class)
- `ContentLayoutAssignmentEntity` - Assignment record (Layout/Entity/)
- `ContentLayoutAssignmentDefinition` - Assignment DAL definition (Layout/Entity/)

## Constraints

### Method Behavior

**LayoutResolver::resolve(RouteMatchResult, ResolvedData, SalesChannelContext): ?string**

Returns layout ID or NULL. Never throws on no match - NULL indicates fallback needed upstream.

```php
// Returns null when no assignments match
$layoutId = $resolver->resolve($match, $data, $context);
if ($layoutId === null) {
    // Handle fallback (e.g., throw exception, use system default)
}
```

**LayoutResolver::matchesDirect(ContentLayoutAssignmentEntity, ResolvedData): bool**

Entity type/ID matching logic:
- If `entityType` is NULL → always returns true (route-level default matches everything)
- Tries both `{entityType}_id` and `{entityType}` keys in ResolvedData
- If `entityId` is NULL → wildcard → returns true if entity type exists in resolved data
- Otherwise → exact ID comparison

**LayoutResolver::matchesAssociation(ContentLayoutAssignmentEntity, ResolvedData, SalesChannelContext): bool**

Association path matching logic:
- Requires `associationPath` in format `source.path.to.target` (minimum 2 parts)
- First part = source entity, remaining = association path for DAL
- Loads association via `Criteria::addAssociation()` (triggers DAL query)
- Returns false if association not loaded or empty
- If `entityId` is NULL → wildcard → returns true if ANY associated entities exist
- Otherwise → checks if specific `entityId` in collected IDs

**LayoutResolver::traverseAndCollectIds(Entity, array $remainingParts): array**

Recursive ID collection from nested entity structure:
- Base case: no remaining parts → returns current entity's unique identifier
- Handles EntityCollections → flattens and merges all IDs from items
- Handles single Entity → continues recursion
- Returns empty array if property missing or null
- Returns unique IDs only (duplicates removed)

### Edge Cases and NULL Handling

**Wildcard matching precedence:**
```php
// entityType=NULL → matches EVERYTHING (route-level default)
['entityType' => null, 'entityId' => null, 'associationPath' => null]

// entityType set, entityId=NULL → matches ANY entity of this type
['entityType' => 'product', 'entityId' => null, 'associationPath' => null]

// BOTH set → matches ONLY specific entity
['entityType' => 'product', 'entityId' => 'uuid-123', 'associationPath' => null]
```

**Association path validation:**
- Path must have at least 2 parts: `source.association`
- Single part like `"product"` is invalid → `matchesAssociation()` returns false
- Source entity must exist in ResolvedData → returns false if missing
- Empty string or null `associationPath` should never reach `matchesAssociation()` (filtered by resolve())

**Sales channel filtering:**
- NULL `salesChannelId` in assignment = global (works in all channels)
- When priorities equal, channel-specific ranked higher than global (ORDER BY sales_channel_id DESC)
- Assignment visible if: `sales_channel_id = context OR sales_channel_id IS NULL`

### Common Mistakes

**Mistake: Assuming `addFields()` optimization is safe**
```php
// ❌ WRONG - Returns PartialEntity without getter methods
$criteria->addFields(['id', 'layoutId', 'entityType', ...]);
$assignments = $repo->search($criteria); // Returns PartialEntity instances!

// ✅ CORRECT - Returns full ContentLayoutAssignmentEntity
$criteria = new Criteria();
$assignments = $repo->search($criteria); // Full entities with all methods
```

Lesson: Avoid `addFields()` on root entity queries when code calls entity getter methods. DAL returns PartialEntity for field-selected queries. Using `addFields()` on association criteria is acceptable when only specific fields (like ID) are needed from associations.

**Mistake: Forgetting priority controls evaluation order**
```php
// ❌ WRONG - Lower priority evaluated first, higher priority never reached
['priority' => 10, 'entityType' => 'product', 'entityId' => 'uuid-123']  // Specific
['priority' => 100, 'entityType' => 'product', 'entityId' => null]       // Wildcard

// ✅ CORRECT - Higher priority evaluated first
['priority' => 100, 'entityType' => 'product', 'entityId' => 'uuid-123'] // Specific
['priority' => 80, 'entityType' => 'product', 'entityId' => null]        // Wildcard
```

Lesson: Assign higher priority to more specific assignments. First match wins.

**Mistake: Using route_id = NULL in assignments**
```php
// ❌ WRONG - route_id is NOT NULL constraint, will fail
['routeId' => null, 'entityType' => 'product', ...]

// ✅ CORRECT - Every assignment scoped to specific route
['routeId' => $routeId, 'entityType' => 'product', ...]
```

Lesson: All assignments MUST have valid `route_id`. No global route assignments.

**Mistake: Forgetting association paths need source entity**
```php
// ❌ WRONG - 'categories' alone is invalid (no source entity)
['associationPath' => 'categories']

// ✅ CORRECT - Full path with source entity
['associationPath' => 'product.categories']
```

Lesson: Association paths must be `source.association.path` (minimum 2 parts).

## Query Optimization

**Single query per resolution:**
LayoutResolver loads ALL matching assignments in one query, then iterates in memory. Not one query per assignment.

**Association matching overhead:**
- Direct matching (associationPath = null): No additional queries
- Association matching (associationPath != null): Triggers DAL query per unique path
- DAL caches loaded associations within request context
- Minimize unique association paths to reduce query count

**Performance tips:**
- Use wildcards instead of association matching when possible
- Higher priority assignments evaluated first → put most likely matches at high priority
- Avoid deeply nested association paths (e.g., `product.cat.parent.grandparent`) unless necessary

## Multi-Level Association Support

**Path parsing:**
```php
'product.categories.parent' → source='product', path='categories.parent'
```

First part (`product`) identifies source entity in ResolvedData. Remaining parts (`categories.parent`) passed to DAL for nested loading.

**DAL loading:**
```php
$criteria->addAssociation('categories.parent'); // DAL handles nesting
```

DAL natively supports multi-level paths. No manual iteration needed.

**Traversal:**
LayoutResolver recursively walks loaded structure via `traverseAndCollectIds()`:
- Handles EntityCollection → iterates items, flattens IDs
- Handles single Entity → continues deeper
- Handles mixed (collection → entity → collection) → flattens all

**Arbitrary depth supported:**
- 1 level: `product.manufacturer`
- 2 levels: `product.categories.parent`
- 3+ levels: `product.manufacturer.country.region`

No depth limit imposed by LayoutResolver. DAL and database performance may limit practical depth.

## Quick Reference

- **resolve() return**: Layout ID string or NULL (never throws)
- **matchesDirect()**: Returns bool, checks entity type/ID from ResolvedData
- **matchesAssociation()**: Returns bool, triggers DAL query, checks association IDs
- **First match wins**: Remaining assignments never evaluated after match
- **Wildcard hierarchy**: entityType=NULL > entityType set + entityId=NULL > both set
- **Priority order**: DESC (100 before 80 before 60...)
- **Sales channel**: Global (NULL) ranks lower than channel-specific when priorities equal
- **Route-scoped**: route_id NOT NULL required on all assignments
- **Association path**: Minimum 2 parts (source.association), arbitrary depth supported
- **Performance**: Direct matching faster than association matching (no extra queries)
- **PartialEntity gotcha**: Never use addFields() on assignment queries
